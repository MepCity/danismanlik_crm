<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\Transition;
use App\Domain\Deal\Models\WorkflowRevision;
use App\Domain\Program\Models\DocTemplate;
use App\Domain\Program\Models\Program;
use App\Domain\Program\Models\ProgramVersion;
use App\Domain\Program\Models\ServiceWorkflow;
use App\Domain\Program\Models\ServiceWorkflowStep;
use App\Domain\Program\Services\ServiceWorkflowSnapshot;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class ReferenceDataSeeder extends Seeder
{
    private const GUARD = 'web';

    /** @var list<string> */
    private const PERMISSIONS = [
        'company.manage',
        'lead.manage',
        'interaction.manage',
        'deal.create',
        'deal.assign',
        'deal.transition',
        'deal.view_own',
        'deal.view_team',
        'deal.view_all',
        'deal.view',
        'deal.amount.view',
        'document.upload',
        'document.approve',
        'document.download',
        'document.view',
        'collaboration.manage',
        'task.manage',
        'program.manage',
        'program.view',
        'audit.view_own',
        'audit.view_team',
        'audit.view_all',
        'system.users',
        'system.roles',
        'system.settings',
        'system.templates',
        'access.break_glass.grant',
        'report.view',
        'report.export',
    ];

    /** @var array<string, array{scope: string, permissions: list<string>}> */
    private const ROLES = [
        'Pazarlama' => [
            'scope' => 'own',
            'permissions' => [
                'company.manage', 'lead.manage', 'interaction.manage', 'deal.create',
                'deal.view_own', 'deal.view', 'collaboration.manage', 'task.manage',
                'document.upload', 'document.download', 'document.view',
                'program.view', 'audit.view_own',
                'report.view',
            ],
        ],
        'Proje Yöneticisi' => [
            'scope' => 'team',
            'permissions' => [
                'company.manage', 'interaction.manage', 'deal.transition', 'deal.view_team',
                'deal.view', 'document.upload', 'document.approve', 'document.download',
                'document.view', 'collaboration.manage', 'task.manage', 'program.view',
                'audit.view_team',
                'report.view', 'report.export',
            ],
        ],
        'Şirket Yetkilisi' => [
            'scope' => 'all',
            'permissions' => [
                'company.manage', 'lead.manage', 'interaction.manage', 'deal.create', 'deal.assign',
                'deal.transition', 'deal.view_all', 'deal.view', 'deal.amount.view',
                'document.upload', 'document.approve', 'document.download', 'document.view',
                'collaboration.manage', 'task.manage', 'program.manage', 'program.view',
                'audit.view_all', 'access.break_glass.grant',
                'report.view', 'report.export',
            ],
        ],
        'Sistem Yöneticisi' => [
            'scope' => 'none',
            'permissions' => [
                'company.manage', 'lead.manage', 'interaction.manage', 'deal.create', 'deal.assign',
                'deal.transition', 'document.upload', 'document.approve', 'program.manage',
                'program.view', 'audit.view_all', 'system.users', 'system.roles',
                'system.settings', 'system.templates',
            ],
        ],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $systemUser = $this->seedSystemUser();
            $statuses = $this->seedStatuses();
            $transitions = $this->seedTransitions($statuses);

            $this->seedInitialWorkflowRevision($systemUser, $statuses, $transitions);
            $this->seedRolesAndPermissions();
            $this->seedProgram();
        });
    }

    private function seedSystemUser(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'system-seeder@localhost.invalid'],
            [
                'name' => 'Sistem Tohumlama Kimliği',
                'password' => Hash::make(Str::random(64)),
                'is_active' => false,
                'deactivated_at' => now(),
                'data_scope' => 'none',
            ],
        );
    }

    /** @return array<string, Status> */
    private function seedStatuses(): array
    {
        $definitions = [
            ['type' => 'lead', 'code' => 'new', 'label' => 'Yeni', 'color' => 'info', 'is_initial' => true],
            ['type' => 'lead', 'code' => 'called', 'label' => 'Arandı', 'color' => 'neutral'],
            ['type' => 'lead', 'code' => 'interested', 'label' => 'İlgileniyor', 'color' => 'waiting'],
            ['type' => 'lead', 'code' => 'proposal_sent', 'label' => 'Teklif gönderildi', 'color' => 'waiting'],
            ['type' => 'lead', 'code' => 'won', 'label' => 'İş alındı', 'color' => 'success', 'is_terminal' => true, 'required_fields' => ['program_version_id'], 'converts_to_deal' => true],
            ['type' => 'lead', 'code' => 'lost', 'label' => 'Kaybedildi', 'color' => 'danger', 'is_terminal' => true, 'required_fields' => ['lost_reason']],
            ['type' => 'lead', 'code' => 'callback', 'label' => 'Sonra aranacak', 'color' => 'waiting', 'required_fields' => ['next_call_at', 'owner_user_id']],
            ['type' => 'lead', 'code' => 'do_not_contact', 'label' => 'Aranmak istemiyor', 'color' => 'danger', 'is_terminal' => true],
            ['type' => 'deal', 'code' => 'awaiting_assignment', 'label' => 'Atama bekliyor', 'color' => 'waiting', 'is_initial' => true],
            ['type' => 'deal', 'code' => 'pm_assigned', 'label' => 'PM atandı', 'color' => 'info', 'required_fields' => ['project_manager_id']],
            ['type' => 'deal', 'code' => 'collecting_documents', 'label' => 'Belgeler toplanıyor', 'color' => 'waiting'],
            ['type' => 'deal', 'code' => 'preparing_application', 'label' => 'Başvuru hazırlanıyor', 'color' => 'info'],
            ['type' => 'deal', 'code' => 'awaiting_customer_approval', 'label' => 'Müşteri onayı bekleniyor', 'color' => 'waiting', 'awaits_customer_response' => true],
            ['type' => 'deal', 'code' => 'submitted', 'label' => 'Kuruma gönderildi', 'color' => 'info'],
            ['type' => 'deal', 'code' => 'under_review', 'label' => 'Kurum değerlendirmesinde', 'color' => 'waiting'],
            ['type' => 'deal', 'code' => 'revision_requested', 'label' => 'Revizyon/ek belge bekleniyor', 'color' => 'danger'],
            ['type' => 'deal', 'code' => 'concluded', 'label' => 'Sonuçlandı', 'color' => 'success'],
            ['type' => 'deal', 'code' => 'closed', 'label' => 'Kapandı/iptal', 'color' => 'neutral', 'is_terminal' => true],
        ];

        $statuses = [];
        $sortOrders = ['lead' => 0, 'deal' => 0];

        foreach ($definitions as $definition) {
            $type = $definition['type'];
            $sortOrders[$type]++;

            $status = Status::query()->updateOrCreate(
                ['type' => $type, 'code' => $definition['code']],
                [
                    'label' => $definition['label'],
                    'color' => $definition['color'],
                    'sort_order' => $sortOrders[$type],
                    'is_terminal' => $definition['is_terminal'] ?? false,
                    'is_active' => true,
                    'required_fields' => $definition['required_fields'] ?? [],
                    'is_initial' => $definition['is_initial'] ?? false,
                    'converts_to_deal' => $definition['converts_to_deal'] ?? false,
                    'awaits_customer_response' => $definition['awaits_customer_response'] ?? false,
                ],
            );

            $statuses[$type.'.'.$definition['code']] = $status;
        }

        return $statuses;
    }

    /**
     * @param  array<string, Status>  $statuses
     * @return list<Transition>
     */
    private function seedTransitions(array $statuses): array
    {
        $documentsAccepted = [
            'all' => [[
                'field' => 'deal.required_documents.status',
                'op' => 'all_in',
                'value' => ['accepted', 'not_required'],
            ]],
        ];
        $definitions = [
            ['lead', 'new', 'called', 'lead.manage', null],
            ['lead', 'called', 'interested', 'lead.manage', null],
            ['lead', 'called', 'callback', 'lead.manage', null],
            ['lead', 'called', 'do_not_contact', 'lead.manage', null],
            ['lead', 'called', 'new', 'lead.manage', null],
            ['lead', 'interested', 'proposal_sent', 'lead.manage', null],
            ['lead', 'interested', 'callback', 'lead.manage', null],
            ['lead', 'interested', 'lost', 'lead.manage', null],
            ['lead', 'interested', 'do_not_contact', 'lead.manage', null],
            ['lead', 'interested', 'called', 'lead.manage', null],
            ['lead', 'interested', 'new', 'lead.manage', null],
            ['lead', 'proposal_sent', 'won', 'lead.manage', null],
            ['lead', 'proposal_sent', 'lost', 'lead.manage', null],
            ['lead', 'proposal_sent', 'callback', 'lead.manage', null],
            ['lead', 'proposal_sent', 'interested', 'lead.manage', null],
            ['lead', 'proposal_sent', 'called', 'lead.manage', null],
            ['lead', 'proposal_sent', 'new', 'lead.manage', null],
            ['lead', 'callback', 'called', 'lead.manage', null],
            ['lead', 'callback', 'interested', 'lead.manage', null],
            ['lead', 'callback', 'new', 'lead.manage', null],
            ['lead', 'callback', 'do_not_contact', 'lead.manage', null],
            ['deal', 'awaiting_assignment', 'pm_assigned', 'deal.assign', null],
            ['deal', 'pm_assigned', 'collecting_documents', 'deal.transition', null],
            ['deal', 'collecting_documents', 'preparing_application', 'deal.transition', $documentsAccepted],
            ['deal', 'preparing_application', 'awaiting_customer_approval', 'deal.transition', null],
            ['deal', 'awaiting_customer_approval', 'preparing_application', 'deal.transition', null],
            ['deal', 'awaiting_customer_approval', 'submitted', 'deal.transition', null],
            ['deal', 'submitted', 'under_review', 'deal.transition', null],
            ['deal', 'under_review', 'revision_requested', 'deal.transition', null],
            ['deal', 'under_review', 'concluded', 'deal.transition', null],
            ['deal', 'revision_requested', 'preparing_application', 'deal.transition', null],
            ['deal', 'concluded', 'closed', 'deal.transition', null],
        ];

        $transitions = [];

        foreach ($definitions as [$type, $from, $to, $permission, $condition]) {
            $transitions[] = Transition::query()->updateOrCreate(
                [
                    'from_status_id' => $statuses[$type.'.'.$from]->id,
                    'to_status_id' => $statuses[$type.'.'.$to]->id,
                ],
                [
                    'required_permission' => $permission,
                    'condition' => $condition,
                    'is_active' => true,
                ],
            );
        }

        return $transitions;
    }

    /**
     * @param  array<string, Status>  $statuses
     * @param  list<Transition>  $transitions
     */
    private function seedInitialWorkflowRevision(User $systemUser, array $statuses, array $transitions): void
    {
        $statusSnapshot = collect($statuses)
            ->sortBy(fn (Status $status): string => $status->type.'.'.str_pad((string) $status->sort_order, 3, '0', STR_PAD_LEFT))
            ->map(fn (Status $status): array => [
                'type' => $status->type,
                'code' => $status->code,
                'label' => $status->label,
                'color' => $status->color,
                'sort_order' => $status->sort_order,
                'is_terminal' => $status->is_terminal,
                'is_active' => $status->is_active,
            ])->values()->all();

        $transitionSnapshot = collect($transitions)
            ->map(function (Transition $transition): array {
                $transition->loadMissing(['fromStatus', 'toStatus']);

                return [
                    'from' => $transition->fromStatus->type.'.'.$transition->fromStatus->code,
                    'to' => $transition->toStatus->type.'.'.$transition->toStatus->code,
                    'required_permission' => $transition->required_permission,
                    'condition' => $transition->condition,
                    'is_active' => $transition->is_active,
                ];
            })
            ->sortBy(fn (array $transition): string => $transition['from'].'>'.$transition['to'])
            ->values()->all();

        WorkflowRevision::query()->firstOrCreate(
            ['reason' => 'ilk kurulum'],
            [
                'snapshot' => ['statuses' => $statusSnapshot, 'transitions' => $transitionSnapshot],
                'effective_from' => now(),
                'changed_by' => $systemUser->id,
            ],
        );
    }

    private function seedRolesAndPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permissionName) {
            Permission::query()->updateOrCreate(
                ['name' => $permissionName, 'guard_name' => self::GUARD],
                ['is_active' => true],
            );
        }

        foreach (self::ROLES as $roleName => $definition) {
            $role = Role::query()->updateOrCreate(
                ['name' => $roleName, 'guard_name' => self::GUARD],
                ['default_scope' => $definition['scope'], 'is_active' => true],
            );
            $role->syncPermissions($definition['permissions']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function seedProgram(): void
    {
        $workflow = ServiceWorkflow::query()->updateOrCreate(
            ['name' => 'KOSGEB başvuru rehberi'],
            [
                'description' => 'KOSGEB destek dosyalarında hazırlıktan kurul kararına kadar izlenecek genel uygulama akışı.',
                'is_active' => true,
            ],
        );
        $steps = [
            ['action', 'Belgeleri topla ve doğrula', 'Hizmete ait belge listesini firma yetkilisiyle paylaşın; gelen sürümleri kontrol ederek eksikleri tamamlatın.', 'Unvan, imza, tarih ve güncellik kontrollerini atlamayın.'],
            ['action', 'Kurum sistemine veri girişi yap', 'Onaylanan bilgileri ve belgeleri ilgili KOSGEB ekranına eksiksiz aktarın.', 'Sisteme girilen tutarların belge ve başvuru formuyla aynı olduğunu karşılaştırın.'],
            ['decision', 'Başvuru ön kontrolünü tamamla', 'Gönderim öncesi zorunlu alanları, ekleri ve yetkili onaylarını ikinci kez kontrol edin.', null],
            ['waiting', 'Değerlendirme ve kurul sonucunu bekle', 'Kurum bildirimlerini izleyin; ek bilgi talebi gelirse dosyada görev açarak süre içinde yanıtlayın.', 'Bildirim ve son yanıt tarihlerini dosya etkinliğine kaydedin.'],
        ];

        foreach ($steps as $order => [$type, $title, $guidance, $attention]) {
            ServiceWorkflowStep::query()->updateOrCreate(
                ['service_workflow_id' => $workflow->id, 'title' => $title],
                [
                    'type' => $type,
                    'guidance' => $guidance,
                    'attention_note' => $attention,
                    'sort_order' => $order,
                    'is_active' => true,
                ],
            );
        }

        $program = Program::query()->updateOrCreate(
            ['code' => 'KOSGEB-YESIL-SANAYI'],
            [
                'name' => 'KOSGEB Yeşil Sanayi Destek Programı',
                'institution' => 'kosgeb',
                'is_active' => true,
            ],
        );
        $version = ProgramVersion::query()->updateOrCreate(
            ['program_id' => $program->id, 'call_period' => '2026 çağrısı'],
            [
                'service_workflow_id' => $workflow->id,
                'application_opens_at' => '2026-01-15 09:00:00',
                'application_closes_at' => '2026-12-15 17:00:00',
                'description' => '2026 çağrısı için başlangıç referans sürümü.',
                'workflow_snapshot' => app(ServiceWorkflowSnapshot::class)->capture($workflow->id),
                'is_active' => true,
            ],
        );

        $earthquakeCities = ['Adana', 'Adıyaman', 'Diyarbakır', 'Elazığ', 'Gaziantep', 'Hatay', 'Malatya', 'Kahramanmaraş', 'Şanlıurfa', 'Kilis', 'Osmaniye'];
        $templates = [
            ['YMM / Bağımsız Denetçi Bildirim Formu', true, null, ['pdf'], null],
            ['Bağlantı Anlaşmasına Çağrı Mektubu', true, null, ['pdf'], null],
            ['Findeks Raporu', true, null, ['pdf'], 30],
            ['Dünya Bankası Çevresel ve Sosyal Durum Tespiti Formu', true, null, ['pdf', 'docx'], null],
            ['Hasar Durumu Belgesi', false, ['all' => [['field' => 'company.city', 'op' => 'in', 'value' => $earthquakeCities]]], ['pdf'], null],
            ['Fizibilite Raporu', false, ['all' => [['field' => 'deal.requested_amount', 'op' => 'gt', 'value' => 5_000_000]]], ['pdf', 'xlsx'], null],
            ['Proje Başvuru Formu', true, null, ['pdf'], null],
        ];

        foreach ($templates as $index => [$name, $required, $condition, $formats, $validityDays]) {
            DocTemplate::query()->updateOrCreate(
                ['program_version_id' => $version->id, 'name' => $name],
                [
                    'description' => null,
                    'is_required' => $required,
                    'condition' => $condition,
                    'accepted_formats' => $formats,
                    'validity_days' => $validityDays,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }
}
