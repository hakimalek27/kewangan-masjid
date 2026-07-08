<?php

namespace Tests\Feature\Ai;

use App\Ai\AiClientFactory;
use App\Ai\Dialects\OpenAiDialect;
use App\Models\AppUser;
use App\Models\BankAccount;
use App\Models\SpProvider;
use App\Services\Ai\KuotaPenyataService;
use App\Services\Ai\SemakPenyataService;
use App\Services\Security\SecretVaultService;
use App\Support\Setting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Multi-provider "Semak Penyata (AI)" — superadmin simpan beberapa profil provider,
 * bendahari pilih provider semasa muat naik (banding OCR). Penyata SAMA boleh discan
 * sekali per-provider (dedup per-provider).
 */
class SemakPenyataProviderTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private int $masjid;
    private int $bankId;
    private AppUser $admin;
    private AppUser $bendahari;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
        $this->masjid = (int) config('spkm.masjid_id');
        $this->bankId = (int) BankAccount::withoutMasjidScope()->where('masjid_id', $this->masjid)->value('id');
        $this->admin = $this->buat('admin');
        $this->bendahari = $this->buat('bendahari');
        Setting::set('semak_penyata_enabled', 'on', KuotaPenyataService::MASJID_GLOBAL);
        Storage::fake('local');
    }

    private function buat(string $role): AppUser
    {
        return AppUser::create([
            'masjid_id' => $this->masjid, 'login' => 'spp_'.$role.'_'.uniqid(),
            'nama_penuh' => 'Uji', 'role' => $role,
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);
    }

    private function buatProvider(array $ubah = []): SpProvider
    {
        return SpProvider::create(array_merge([
            'nama' => 'Prov '.uniqid(), 'dialect' => 'openai', 'model' => 'gpt-4o',
            'base_url' => null, 'api_key_ref' => app(SecretVaultService::class)->put('sk-uji'),
            'is_active' => true, 'is_default' => false,
        ], $ubah));
    }

    public function test_forpusat_pilih_provider_tertentu(): void
    {
        $p = $this->buatProvider(['nama' => 'DeepSeek', 'model' => 'deepseek-chat', 'base_url' => 'https://api.deepseek.com']);

        [$extractor, $config] = app(AiClientFactory::class)->forPusat($p->id);

        $this->assertInstanceOf(OpenAiDialect::class, $extractor);
        $this->assertSame('DeepSeek', $config['provider']);
        $this->assertSame('deepseek-chat', $config['model']);
        $this->assertSame('https://api.deepseek.com', $config['base_url']);
        $this->assertSame($p->id, $config['sp_provider_id']);
    }

    public function test_forpusat_default_pilih_is_default(): void
    {
        $this->buatProvider(['nama' => 'Biasa', 'is_default' => false]);
        $def = $this->buatProvider(['nama' => 'Utama', 'is_default' => true]);

        [, $config] = app(AiClientFactory::class)->forPusat();

        $this->assertSame('Utama', $config['provider']);
        $this->assertSame($def->id, $config['sp_provider_id']);
    }

    public function test_forpusat_fallback_legasi_bila_tiada_profil(): void
    {
        Setting::set('sp_ai_key_ref', app(SecretVaultService::class)->put('sk-legasi'), KuotaPenyataService::MASJID_GLOBAL);
        Setting::set('sp_ai_model', 'gpt-4o-mini', KuotaPenyataService::MASJID_GLOBAL);

        [, $config] = app(AiClientFactory::class)->forPusat();

        $this->assertSame('OPENAI', $config['provider']);
        $this->assertSame(0, $config['sp_provider_id']);
    }

    public function test_muat_naik_simpan_provider_dan_dedup_per_provider(): void
    {
        Queue::fake();
        $p1 = $this->buatProvider(['nama' => 'OpenAI']);
        $p2 = $this->buatProvider(['nama' => 'DeepSeek', 'base_url' => 'https://api.deepseek.com']);
        $servis = app(SemakPenyataService::class);

        // Fail saiz sama → kandungan & hash SAMA (UploadedFile::fake corak tetap).
        $b1 = $servis->muatNaik($this->bankId, UploadedFile::fake()->create('p.pdf', 100, 'application/pdf'), $p1->id);
        $this->assertSame($p1->id, (int) $b1->sp_provider_id);
        $this->assertStringContainsString('OpenAI', (string) $b1->provider_label);

        // Penyata SAMA, provider LAIN → batch baharu (dibenarkan untuk banding).
        $b2 = $servis->muatNaik($this->bankId, UploadedFile::fake()->create('p.pdf', 100, 'application/pdf'), $p2->id);
        $this->assertNotSame($b1->id, $b2->id);
        $this->assertSame($p2->id, (int) $b2->sp_provider_id);

        // Penyata SAMA + provider SAMA (bukan GAGAL) → ditolak.
        $this->expectException(InvalidArgumentException::class);
        $servis->muatNaik($this->bankId, UploadedFile::fake()->create('p.pdf', 100, 'application/pdf'), $p1->id);
    }

    public function test_muat_naik_tanpa_pilih_guna_default(): void
    {
        Queue::fake();
        $def = $this->buatProvider(['nama' => 'Default AI', 'is_default' => true]);

        $b = app(SemakPenyataService::class)->muatNaik($this->bankId, UploadedFile::fake()->create('x.pdf', 80, 'application/pdf'), null);

        $this->assertSame($def->id, (int) $b->sp_provider_id);
    }

    public function test_admin_simpan_provider_dan_default_tunggal(): void
    {
        $this->actingAs($this->admin)->post(route('admin.semakpenyata.provider'), [
            'nama' => 'OpenAI', 'model' => 'gpt-4o', 'api_key' => 'sk-abc', 'is_active' => '1', 'is_default' => '1',
        ])->assertRedirect(route('admin.semakpenyata'));
        $this->assertDatabaseHas('sp_provider', ['nama' => 'OpenAI', 'is_default' => 1]);

        // Provider kedua jadi default → yang pertama tidak lagi default (satu sahaja).
        $this->actingAs($this->admin)->post(route('admin.semakpenyata.provider'), [
            'nama' => 'DeepSeek', 'model' => 'deepseek-chat', 'base_url' => 'https://api.deepseek.com',
            'api_key' => 'sk-ds', 'is_active' => '1', 'is_default' => '1',
        ]);

        $this->assertSame(1, SpProvider::where('is_default', true)->count());
        $this->assertSame('DeepSeek', SpProvider::where('is_default', true)->first()->nama);
    }

    public function test_admin_padam_provider(): void
    {
        $p = $this->buatProvider();
        $this->actingAs($this->admin)->post(route('admin.semakpenyata.provider.padam', $p->id))->assertRedirect();
        $this->assertDatabaseMissing('sp_provider', ['id' => $p->id]);
    }

    public function test_bendahari_tak_boleh_urus_provider(): void
    {
        $this->actingAs($this->bendahari)->post(route('admin.semakpenyata.provider'), [
            'nama' => 'Haram', 'model' => 'gpt-4o', 'api_key' => 'sk-x',
        ])->assertForbidden();
        $this->assertDatabaseMissing('sp_provider', ['nama' => 'Haram']);
    }
}
