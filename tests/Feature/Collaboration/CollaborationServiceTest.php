<?php

declare(strict_types=1);

use App\Domain\Collaboration\DTOs\SubjectReference;
use App\Domain\Collaboration\Enums\CollaborationSubjectType;
use App\Domain\Collaboration\Jobs\SendNotificationEmail;
use App\Domain\Collaboration\Models\Activity;
use App\Domain\Collaboration\Models\Notification;
use App\Domain\Collaboration\Services\ActivityTranslator;
use App\Domain\Collaboration\Services\CommentService;
use App\Domain\Collaboration\Services\DailyDigestService;
use App\Domain\Collaboration\Services\DueTaskReminder;
use App\Domain\Collaboration\Services\EmailNotificationService;
use App\Domain\Collaboration\Services\TaskService;
use App\Domain\Collaboration\Services\TimelineQuery;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Program\Models\ProgramVersion;
use App\Models\User;
use App\Support\Audit\ActorHolder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery\Expectation;
use Symfony\Component\Mime\Email;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('tr');
    app(ReferenceDataSeeder::class)->run();
    Queue::fake();
});

/** @return array{owner: User, officer: User, outsider: User, company: Company, lead: Lead, deal: Deal, document: DealDocument} */
function collaborationFixture(string $suffix = 'bir'): array
{
    $owner = User::factory()->create(['name' => 'Kurgusal Pazarlamacı', 'email' => "pazarlama-{$suffix}@example.invalid"]);
    $owner->assignRole('Pazarlama');
    $officer = User::factory()->create(['name' => 'Kurgusal Yetkili', 'email' => "yetkili-{$suffix}@example.invalid"]);
    $officer->assignRole('Şirket Yetkilisi');
    $outsider = User::factory()->create(['name' => 'Kurgusal Dış Kullanıcı', 'email' => "dis-{$suffix}@example.invalid"]);
    $outsider->assignRole('Pazarlama');
    $company = Company::query()->create(['legal_name' => 'Kurgusal Ufuk İşletmesi '.$suffix, 'city' => 'Ankara']);
    $leadStatus = Status::query()->where('type', 'lead')->firstOrFail();
    $dealStatus = Status::query()->where('type', 'deal')->firstOrFail();
    $version = ProgramVersion::query()->firstOrFail();
    $lead = Lead::query()->create([
        'company_id' => $company->id,
        'owner_user_id' => $owner->id,
        'status_id' => $leadStatus->id,
    ]);
    $deal = Deal::query()->create([
        'company_id' => $company->id,
        'program_version_id' => $version->id,
        'reference_no' => 'KRG-'.$suffix,
        'status_id' => $dealStatus->id,
        'status_changed_at' => now(),
        'opened_by_user_id' => $owner->id,
        'pm_user_id' => $officer->id,
        'requested_amount' => 987654.32,
    ]);
    $document = DealDocument::query()->create([
        'deal_id' => $deal->id,
        'source_program_version_id' => $version->id,
        'name_snapshot' => 'Kurgusal Fizibilite Belgesi',
        'description_snapshot' => 'E-postaya girmemesi gereken evrak içeriği',
        'required_snapshot' => true,
        'status' => 'requested',
    ]);

    return compact('owner', 'officer', 'outsider', 'company', 'lead', 'deal', 'document');
}

it('yorumu oluşturur ve önceki sürümü audit kaydında koruyarak düzenler', function (): void {
    $fixture = collaborationFixture();
    $subject = new SubjectReference(CollaborationSubjectType::Deal, $fixture['deal']->id);
    $service = app(CommentService::class);
    $comment = $service->create($fixture['owner'], $subject, 'İlk kurgusal not.');
    $edited = $service->edit($fixture['owner'], $comment, 'Güncel kurgusal not.');
    $audit = DB::table('audit_log')->where('table_name', 'comments')->where('row_id', $comment->id)
        ->where('operation', 'UPDATE')->latest('created_at')->first();

    expect($edited->edited_at)->not->toBeNull()
        ->and($edited->body)->toBe('Güncel kurgusal not.')
        ->and($audit)->not->toBeNull()
        ->and((int) $audit->actor_id)->toBe($fixture['owner']->id)
        ->and(json_decode((string) $audit->old_data, true)['body'])->toBe('İlk kurgusal not.');
});

it('kapsam dışı bahsetmeyi sessizce çıkarır ve bilgi sızdırmaz', function (): void {
    $fixture = collaborationFixture();
    $body = "Gizli yorum @[Kurgusal Dış Kullanıcı](user:{$fixture['outsider']->id})";
    $comment = app(CommentService::class)->create(
        $fixture['owner'],
        new SubjectReference(CollaborationSubjectType::Deal, $fixture['deal']->id),
        $body,
    );

    expect($comment->mentions)->toBe([])
        ->and(Notification::query()->where('user_id', $fixture['outsider']->id)->exists())->toBeFalse();
});

it('erişebilen kullanıcıya bahsetme bildirimi üretir', function (): void {
    $fixture = collaborationFixture();
    $body = "Kontrol eder misiniz @[Kurgusal Yetkili](user:{$fixture['officer']->id})";
    $comment = app(CommentService::class)->create(
        $fixture['owner'],
        new SubjectReference(CollaborationSubjectType::Deal, $fixture['deal']->id),
        $body,
    );

    expect($comment->mentions)->toBe([$fixture['officer']->id])
        ->and(Notification::query()->where('user_id', $fixture['officer']->id)->where('type', 'comment.mentioned')->exists())->toBeTrue();
});

it('yeni yorum ve yanıtları yalnız iç not olarak kaydeder', function (): void {
    $fixture = collaborationFixture();
    $subject = new SubjectReference(CollaborationSubjectType::Deal, $fixture['deal']->id);
    $service = app(CommentService::class);
    $comment = $service->create($fixture['owner'], $subject, 'Yalnız ekip görür.');
    $reply = $service->create($fixture['officer'], $subject, 'Ekip içi yanıt.', $comment->id);

    expect($comment->visibility)->toBe('internal')
        ->and($reply->visibility)->toBe('internal');
});

it('kapsam dışı özneye yorum yazmayı policy ile reddeder', function (): void {
    $fixture = collaborationFixture();

    expect(fn () => app(CommentService::class)->create(
        $fixture['outsider'],
        new SubjectReference(CollaborationSubjectType::Deal, $fixture['deal']->id),
        'Bu yorum yazılamaz.',
    ))->toThrow(AuthorizationException::class);
});

it('yorum ve görevlerin silinmesini veritabanında da reddeder', function (): void {
    $fixture = collaborationFixture();
    $subject = new SubjectReference(CollaborationSubjectType::Deal, $fixture['deal']->id);
    $comment = app(CommentService::class)->create($fixture['owner'], $subject, 'Silinmeyen yorum.');
    $task = app(TaskService::class)->create($fixture['owner'], $subject, $fixture['officer'], 'Silinmeyen görev', now()->addDay());

    expect(fn () => $comment->delete())->toThrow(QueryException::class)
        ->and(fn () => $task->delete())->toThrow(QueryException::class);
});

it('görevi atar tamamlar ve yeniden açar', function (): void {
    $fixture = collaborationFixture();
    $subject = new SubjectReference(CollaborationSubjectType::Deal, $fixture['deal']->id);
    $service = app(TaskService::class);
    $task = $service->create($fixture['owner'], $subject, $fixture['officer'], 'Kurgusal kontrol', now()->addDay());
    $task = $service->assign($fixture['owner'], $task, $fixture['owner']);
    $task = $service->complete($fixture['owner'], $task);

    expect($task->assigned_to)->toBe($fixture['owner']->id)->and($task->completed_at)->not->toBeNull();

    expect($service->reopen($fixture['owner'], $task)->completed_at)->toBeNull();
});

it('son tarih olmadan görev oluşturur', function (): void {
    $fixture = collaborationFixture();
    $subject = new SubjectReference(CollaborationSubjectType::Deal, $fixture['deal']->id);

    $task = app(TaskService::class)->create(
        $fixture['owner'],
        $subject,
        $fixture['officer'],
        'Tarihsiz kurgusal görev',
    );

    expect($task->due_at)->toBeNull();
});

it('vakti gelen görev için uygulama içi ve e-posta bildirimi bir kez üretir', function (): void {
    $fixture = collaborationFixture();
    $subject = new SubjectReference(CollaborationSubjectType::Deal, $fixture['deal']->id);
    app(TaskService::class)->create(
        $fixture['owner'], $subject, $fixture['officer'], 'Kurgusal hatırlatma', now()->addHour(), now()->subMinute(),
    );
    $service = app(DueTaskReminder::class);

    expect($service->run())->toBe(1)->and($service->run())->toBe(0)
        ->and(Notification::query()->where('type', 'task.reminder')->where('channel', 'in_app')->count())->toBe(1)
        ->and(Notification::query()->where('type', 'task.reminder')->where('channel', 'email')->count())->toBe(1);
    Queue::assertPushed(SendNotificationEmail::class);
});

it('e-posta işini gönderir ve teslim durumunu sent yapar', function (): void {
    Queue::fake();
    config()->set('app.name', 'Bizlife CRM');
    $user = User::factory()->create(['email' => 'teslim@example.invalid']);
    $notification = app(EmailNotificationService::class)->queue($user, 'test', 'Kurgusal başlık', 'Kurgusal gövde');
    $mailer = Mockery::mock(Mailer::class);
    /** @var Expectation $mailExpectation */
    $mailExpectation = $mailer->shouldReceive('raw');
    $mailExpectation->once()->with("Bizlife CRM\n\nKurgusal gövde", Mockery::on(function (callable $callback): bool {
        $email = new Email;
        $callback(new Message($email));

        return $email->getSubject() === 'Bizlife CRM · Kurgusal başlık'
            && $email->getFrom()[0]->getName() === 'Bizlife CRM';
    }));
    app()->instance(Mailer::class, $mailer);

    (new SendNotificationEmail($notification->id))->handle(app(ActorHolder::class));

    expect($notification->refresh()->delivery_status)->toBe('sent')->and($notification->error)->toBeNull();
});

it('geçici gönderim hatasını kaydeder ve sonraki denemede düzelir', function (): void {
    $user = User::factory()->create(['email' => 'yeniden-deneme@example.invalid']);
    $notification = Notification::query()->create([
        'user_id' => $user->id,
        'type' => 'test.retry',
        'title' => 'Kurgusal yeniden deneme',
        'body' => 'Kurgusal güvenli içerik',
        'channel' => 'email',
    ]);
    $mailer = Mockery::mock(Mailer::class);
    /** @var Expectation $failedExpectation */
    $failedExpectation = $mailer->shouldReceive('raw');
    $failedExpectation->once()->andThrow(new RuntimeException('Kurgusal SMTP kesintisi'));
    app()->instance(Mailer::class, $mailer);
    $job = new SendNotificationEmail($notification->id);

    expect(fn () => $job->handle(app(ActorHolder::class)))->toThrow(RuntimeException::class);
    expect($notification->refresh()->delivery_status)->toBe('failed')->and($notification->error)->toContain('SMTP');

    $successful = Mockery::mock(Mailer::class);
    $successful->expects('raw');
    app()->instance(Mailer::class, $successful);
    $job->handle(app(ActorHolder::class));

    expect($notification->refresh()->delivery_status)->toBe('sent')->and($notification->error)->toBeNull();
});

it('günlük özeti doğru kullanıcıya yalnız asgari veriyle hazırlar', function (): void {
    $fixture = collaborationFixture();
    app(TaskService::class)->create(
        $fixture['owner'],
        new SubjectReference(CollaborationSubjectType::Deal, $fixture['deal']->id),
        $fixture['officer'],
        'Telefon 0555 000 00 00 mali veri 987654 evrak içeriği',
        now()->addDay(),
        description: 'Gizli evrak içeriği',
    );

    expect(app(DailyDigestService::class)->run())->toBeGreaterThanOrEqual(1);
    $digest = Notification::query()->where('user_id', $fixture['officer']->id)->where('type', 'daily_digest')->sole();

    expect($digest->body)->toContain($fixture['company']->legal_name, $fixture['deal']->reference_no);
    expect($digest->body)->not->toContain('0555', '987654', 'evrak içeriği', 'Gizli');
});

it('Mailpit üzerinden gerçek SMTP teslimini doğrular', function (): void {
    $mailpitHost = (string) config('mail.mailers.smtp.host');
    Http::delete("http://{$mailpitHost}:8025/api/v1/messages");
    config()->set('app.name', 'Bizlife CRM');
    config()->set('mail.default', 'smtp');
    config()->set('mail.mailers.smtp.host', $mailpitHost);
    config()->set('mail.mailers.smtp.port', 1025);
    app('mail.manager')->forgetMailers();
    $user = User::factory()->create(['email' => 'mailpit-teslim@example.invalid']);
    $notification = Notification::query()->create([
        'user_id' => $user->id,
        'type' => 'test.mailpit',
        'title' => 'Kurgusal Mailpit teslimi',
        'body' => 'Yalnız kurgusal ve asgari içerik',
        'channel' => 'email',
    ]);

    (new SendNotificationEmail($notification->id))->handle(app(ActorHolder::class));
    /** @var list<array{Subject: string}> $messages */
    $messages = Http::get("http://{$mailpitHost}:8025/api/v1/messages")->throw()->json('messages');

    expect($notification->refresh()->delivery_status)->toBe('sent')
        ->and(array_column($messages, 'Subject'))->toContain('Bizlife CRM · Kurgusal Mailpit teslimi');
});

it('aktiviteyi anlık görüntüden çevirir ve statü değişikliklerinden etkilenmez', function (): void {
    $fixture = collaborationFixture();
    $activity = Activity::query()->create([
        'actor_id' => $fixture['owner']->id,
        'deal_id' => $fixture['deal']->id,
        'action' => 'deal.status_changed',
        'payload' => [
            'from_status' => ['id' => 10, 'label' => 'Atama bekliyor'],
            'to_status' => ['id' => 11, 'label' => 'PM atandı'],
        ],
        'source' => 'user',
    ]);
    $before = app(ActivityTranslator::class)->sentence($activity->action, $activity->payload);
    $fixture['deal']->status->update(['label' => 'Sonradan değişti', 'is_active' => false]);
    $after = app(ActivityTranslator::class)->sentence($activity->action, $activity->payload);

    expect($before)->toBe('statüyü "Atama bekliyor" → "PM atandı" yaptı')->and($after)->toBe($before);
});

it('bilinmeyen olayda ham JSON göstermeden yedek cümle üretir', function (): void {
    $sentence = app(ActivityTranslator::class)->sentence('legacy.unknown_event', ['secret' => 'ham-json-değeri']);

    expect($sentence)->toBe('legacy unknown event işlemini gerçekleştirdi');
    expect($sentence)->not->toContain('secret', '{', '}');
});

it('otomasyon kaynağını Sistem olarak gösterir', function (): void {
    expect(app(ActivityTranslator::class)->actor('Kurgusal Kullanıcı', 'automation'))->toBe('Sistem');
});

it('zaman tünelini kapsamlı, filtrelenebilir ve sabit sorgu sayısıyla sayfalar', function (): void {
    $fixture = collaborationFixture();
    $subject = new SubjectReference(CollaborationSubjectType::Deal, $fixture['deal']->id);
    app(CommentService::class)->create($fixture['owner'], $subject, 'Kurgusal zaman tüneli yorumu.');
    foreach (range(1, 12) as $index) {
        Activity::query()->create([
            'actor_id' => $fixture['owner']->id,
            'deal_id' => $fixture['deal']->id,
            'action' => 'deal.status_changed',
            'payload' => ['from_status' => ['label' => "Eski {$index}"], 'to_status' => ['label' => "Yeni {$index}"]],
            'source' => 'user',
        ]);
    }
    $query = app(TimelineQuery::class);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $first = $query->paginate($fixture['owner'], $subject, perPage: 5);
    $firstCount = count(DB::getQueryLog());
    DB::flushQueryLog();
    $second = $query->paginate($fixture['owner'], $subject, perPage: 25);
    $secondCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($first->total())->toBe(13)->and($first->count())->toBe(5)
        ->and($second->total())->toBe(13)->and($firstCount)->toBe(5)->and($secondCount)->toBe(5)
        ->and($query->paginate($fixture['owner'], $subject, 'comment')->total())->toBe(1)
        ->and($query->paginate($fixture['owner'], $subject, 'status')->total())->toBe(12)
        ->and(fn () => $query->paginate($fixture['outsider'], $subject))->toThrow(AuthorizationException::class);
});

it('firma zaman tünelinde firma fırsat proje ve evrak hareketlerini birleştirir', function (): void {
    $fixture = collaborationFixture();
    $subject = new SubjectReference(CollaborationSubjectType::Company, $fixture['company']->id);

    app(CommentService::class)->create($fixture['owner'], $subject, 'Firma geneli kurgusal not.');
    Activity::query()->create([
        'actor_id' => $fixture['owner']->id,
        'lead_id' => $fixture['lead']->id,
        'action' => 'lead.created',
        'payload' => ['company' => ['label' => $fixture['company']->legal_name]],
        'source' => 'user',
    ]);
    Activity::query()->create([
        'actor_id' => $fixture['officer']->id,
        'deal_id' => $fixture['deal']->id,
        'action' => 'deal.assigned',
        'payload' => ['assignee' => ['label' => $fixture['officer']->name]],
        'source' => 'user',
    ]);
    Activity::query()->create([
        'actor_id' => $fixture['officer']->id,
        'deal_document_id' => $fixture['document']->id,
        'action' => 'document.requested',
        'payload' => ['document' => ['label' => $fixture['document']->name_snapshot]],
        'source' => 'user',
    ]);

    $timeline = app(TimelineQuery::class)->paginate($fixture['owner'], $subject);

    expect($timeline->total())->toBe(4)
        ->and($timeline->getCollection()->pluck('type')->all())->toContain('comment', 'activity');
});
