<?php

declare(strict_types=1);

namespace App\Support\Collaboration;

use App\Domain\Collaboration\Models\Notification;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Deal;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Program\Models\Program;
use App\Filament\Pages\DealDetail;
use App\Filament\Pages\LeadDetail;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Programs\ProgramResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class NotificationUrlResolver
{
    /**
     * Kullanıcının bildirimi ve içeriğini görme yetkisi olup olmadığını denetler.
     * Kapsam dışı veya silinmiş/pasif hedeflerde false döner.
     */
    public function isAccessible(User $user, Notification $notification): bool
    {
        if ($notification->user_id !== $user->id) {
            return false;
        }

        if ($notification->deal_document_id !== null) {
            $dealDocument = DealDocument::query()->find($notification->deal_document_id);
            if ($dealDocument === null || ! Gate::forUser($user)->allows('view', $dealDocument)) {
                return false;
            }
        }

        if ($notification->deal_id !== null) {
            $deal = Deal::query()->find($notification->deal_id);
            if ($deal === null || ! Gate::forUser($user)->allows('view', $deal)) {
                return false;
            }
        }

        if ($notification->lead_id !== null) {
            $lead = Lead::query()->find($notification->lead_id);
            if ($lead === null || ! Gate::forUser($user)->allows('view', $lead)) {
                return false;
            }
        }

        $companyId = $notification->getAttribute('company_id');
        if ($companyId !== null) {
            $company = Company::query()->find($companyId);
            if ($company === null || ! Gate::forUser($user)->allows('view', $company)) {
                return false;
            }
        }

        $programId = $notification->getAttribute('program_id');
        if ($programId !== null) {
            $program = Program::query()->find($programId);
            if ($program === null || ! Gate::forUser($user)->allows('view', $program)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verilen bildirim listesini N+1 sorgusu üretmeden toplu olarak yetki kontrolünden geçirir.
     *
     * @param  EloquentCollection<int, Notification>|Collection<int, Notification>  $notifications
     * @return EloquentCollection<int, Notification>
     */
    public function filterAccessible(User $user, EloquentCollection|Collection $notifications): EloquentCollection
    {
        if ($notifications->isEmpty()) {
            return new EloquentCollection;
        }

        $docIds = $notifications->pluck('deal_document_id')->filter()->unique()->all();
        $dealIds = $notifications->pluck('deal_id')->filter()->unique()->all();
        $leadIds = $notifications->pluck('lead_id')->filter()->unique()->all();
        $companyIds = $notifications->map(fn (Notification $n) => $n->getAttribute('company_id'))->filter()->unique()->all();
        $programIds = $notifications->map(fn (Notification $n) => $n->getAttribute('program_id'))->filter()->unique()->all();

        $dealDocs = ! empty($docIds) ? DealDocument::query()->whereIn('id', $docIds)->with('deal')->get()->keyBy('id') : collect();
        $deals = ! empty($dealIds) ? Deal::query()->whereIn('id', $dealIds)->get()->keyBy('id') : collect();
        $leads = ! empty($leadIds) ? Lead::query()->whereIn('id', $leadIds)->get()->keyBy('id') : collect();
        $companies = ! empty($companyIds) ? Company::query()->whereIn('id', $companyIds)->get()->keyBy('id') : collect();
        $programs = ! empty($programIds) ? Program::query()->whereIn('id', $programIds)->get()->keyBy('id') : collect();

        /** @var EloquentCollection<int, Notification> $filtered */
        $filtered = $notifications->filter(function (Notification $notification) use ($user, $dealDocs, $deals, $leads, $companies, $programs): bool {
            if ($notification->user_id !== $user->id) {
                return false;
            }

            if ($notification->deal_document_id !== null) {
                $doc = $dealDocs->get($notification->deal_document_id);
                if ($doc === null || ! Gate::forUser($user)->allows('view', $doc)) {
                    return false;
                }
            }

            if ($notification->deal_id !== null) {
                $deal = $deals->get($notification->deal_id);
                if ($deal === null || ! Gate::forUser($user)->allows('view', $deal)) {
                    return false;
                }
            }

            if ($notification->lead_id !== null) {
                $lead = $leads->get($notification->lead_id);
                if ($lead === null || ! Gate::forUser($user)->allows('view', $lead)) {
                    return false;
                }
            }

            $companyId = $notification->getAttribute('company_id');
            if ($companyId !== null) {
                $company = $companies->get($companyId);
                if ($company === null || ! Gate::forUser($user)->allows('view', $company)) {
                    return false;
                }
            }

            $programId = $notification->getAttribute('program_id');
            if ($programId !== null) {
                $program = $programs->get($programId);
                if ($program === null || ! Gate::forUser($user)->allows('view', $program)) {
                    return false;
                }
            }

            return true;
        })->values();

        return new EloquentCollection($filtered->all());
    }

    /**
     * Bildirimin hedef sayfasını çözer. Yetkisiz, kapsam dışı veya geçersiz hedeflerde null döner.
     */
    public function resolve(User $user, Notification $notification): ?string
    {
        if (! $this->isAccessible($user, $notification)) {
            return null;
        }

        if ($notification->deal_document_id !== null) {
            $dealDocument = DealDocument::query()->find($notification->deal_document_id);
            if ($dealDocument !== null) {
                return DealDetail::getUrl(['deal' => $dealDocument->deal_id]);
            }
        }

        if ($notification->deal_id !== null) {
            return DealDetail::getUrl(['deal' => $notification->deal_id]);
        }

        if ($notification->lead_id !== null) {
            return LeadDetail::getUrl(['lead' => $notification->lead_id]);
        }

        $companyId = $notification->getAttribute('company_id');
        if ($companyId !== null) {
            return CompanyResource::getUrl('view', ['record' => $companyId]);
        }

        $programId = $notification->getAttribute('program_id');
        if ($programId !== null) {
            $program = Program::query()->find($programId);
            if ($program !== null) {
                if (Gate::forUser($user)->allows('update', $program)) {
                    return ProgramResource::getUrl('edit', ['record' => $programId]);
                }
                if (Gate::forUser($user)->allows('view', $program)) {
                    return ProgramResource::getUrl('view', ['record' => $programId]);
                }
            }
        }

        return null;
    }
}
