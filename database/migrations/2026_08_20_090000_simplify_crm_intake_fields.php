<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PROVINCES = [
        '01' => 'Adana', '02' => 'Adıyaman', '03' => 'Afyonkarahisar', '04' => 'Ağrı', '05' => 'Amasya', '06' => 'Ankara', '07' => 'Antalya', '08' => 'Artvin', '09' => 'Aydın', '10' => 'Balıkesir',
        '11' => 'Bilecik', '12' => 'Bingöl', '13' => 'Bitlis', '14' => 'Bolu', '15' => 'Burdur', '16' => 'Bursa', '17' => 'Çanakkale', '18' => 'Çankırı', '19' => 'Çorum', '20' => 'Denizli',
        '21' => 'Diyarbakır', '22' => 'Edirne', '23' => 'Elazığ', '24' => 'Erzincan', '25' => 'Erzurum', '26' => 'Eskişehir', '27' => 'Gaziantep', '28' => 'Giresun', '29' => 'Gümüşhane', '30' => 'Hakkâri',
        '31' => 'Hatay', '32' => 'Isparta', '33' => 'Mersin', '34' => 'İstanbul', '35' => 'İzmir', '36' => 'Kars', '37' => 'Kastamonu', '38' => 'Kayseri', '39' => 'Kırklareli', '40' => 'Kırşehir',
        '41' => 'Kocaeli', '42' => 'Konya', '43' => 'Kütahya', '44' => 'Malatya', '45' => 'Manisa', '46' => 'Kahramanmaraş', '47' => 'Mardin', '48' => 'Muğla', '49' => 'Muş', '50' => 'Nevşehir',
        '51' => 'Niğde', '52' => 'Ordu', '53' => 'Rize', '54' => 'Sakarya', '55' => 'Samsun', '56' => 'Siirt', '57' => 'Sinop', '58' => 'Sivas', '59' => 'Tekirdağ', '60' => 'Tokat',
        '61' => 'Trabzon', '62' => 'Tunceli', '63' => 'Şanlıurfa', '64' => 'Uşak', '65' => 'Van', '66' => 'Yozgat', '67' => 'Zonguldak', '68' => 'Aksaray', '69' => 'Bayburt', '70' => 'Karaman',
        '71' => 'Kırıkkale', '72' => 'Batman', '73' => 'Şırnak', '74' => 'Bartın', '75' => 'Ardahan', '76' => 'Iğdır', '77' => 'Yalova', '78' => 'Karabük', '79' => 'Kilis', '80' => 'Osmaniye', '81' => 'Düzce',
    ];

    public function up(): void
    {
        DB::statement('ALTER TABLE companies DROP CONSTRAINT IF EXISTS companies_city_code');
        DB::statement('ALTER TABLE companies ALTER COLUMN city TYPE VARCHAR(50)');

        foreach (self::PROVINCES as $code => $province) {
            DB::table('companies')->where('city', $code)->update(['city' => $province]);
        }
        $provinces = implode(', ', array_map(static fn (string $province): string => DB::getPdo()->quote($province), array_values(self::PROVINCES)));
        DB::statement("ALTER TABLE companies ADD CONSTRAINT companies_city_valid CHECK (city IN ({$provinces}))");

        DB::table('doc_templates')->whereNotNull('condition')->get(['id', 'condition'])->each(function (object $template): void {
            $condition = is_string($template->condition) ? json_decode($template->condition, true) : (array) $template->condition;
            $this->replaceProvinceCodes($condition);
            DB::table('doc_templates')->where('id', $template->id)->update(['condition' => json_encode($condition, JSON_UNESCAPED_UNICODE)]);
        });
        DB::table('statuses')->where('type', 'deal')->where('code', 'pm_assigned')->update(['required_fields' => json_encode(['project_manager_id'])]);

        Schema::table('contacts', fn ($table) => $table->dropColumn('decision_role'));
        Schema::table('communication_consents', fn ($table) => $table->dropColumn('disclosure_method'));
        Schema::table('interactions', fn ($table) => $table->dropColumn('duration_minutes'));
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE companies DROP CONSTRAINT IF EXISTS companies_city_valid');
        Schema::table('contacts', fn ($table) => $table->string('decision_role')->nullable());
        Schema::table('communication_consents', fn ($table) => $table->string('disclosure_method')->nullable());
        Schema::table('interactions', fn ($table) => $table->unsignedInteger('duration_minutes')->nullable());

        DB::table('doc_templates')->whereNotNull('condition')->get(['id', 'condition'])->each(function (object $template): void {
            $condition = is_string($template->condition) ? json_decode($template->condition, true) : (array) $template->condition;
            $this->replaceProvinceNames($condition);
            DB::table('doc_templates')->where('id', $template->id)->update(['condition' => json_encode($condition, JSON_UNESCAPED_UNICODE)]);
        });
        DB::table('statuses')->where('type', 'deal')->where('code', 'pm_assigned')->update(['required_fields' => json_encode([])]);

        foreach (array_flip(self::PROVINCES) as $province => $code) {
            DB::table('companies')->where('city', $province)->update(['city' => $code]);
        }

        DB::statement('ALTER TABLE companies ALTER COLUMN city TYPE CHAR(2)');
        DB::statement("ALTER TABLE companies ADD CONSTRAINT companies_city_code CHECK (city ~ '^(0[1-9]|[1-7][0-9]|8[01])$')");
    }

    /** @param array<string|int, mixed> $condition */
    private function replaceProvinceCodes(array &$condition): void
    {
        foreach ($condition as $key => &$value) {
            if (is_array($value)) {
                if ($key === 'value') {
                    $value = array_map(static fn (mixed $item): mixed => is_string($item) ? (self::PROVINCES[$item] ?? $item) : $item, $value);
                } else {
                    $this->replaceProvinceCodes($value);
                }
            }
        }
    }

    /** @param array<string|int, mixed> $condition */
    private function replaceProvinceNames(array &$condition): void
    {
        $codes = array_flip(self::PROVINCES);

        foreach ($condition as $key => &$value) {
            if (is_array($value)) {
                if ($key === 'value') {
                    $value = array_map(static fn (mixed $item): mixed => is_string($item) ? ($codes[$item] ?? $item) : $item, $value);
                } else {
                    $this->replaceProvinceNames($value);
                }
            }
        }
    }
};
