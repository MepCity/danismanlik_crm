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
            return ProgramResource::getUrl('edit', ['record' => $programId]);
        }

        return null;
    }
}
