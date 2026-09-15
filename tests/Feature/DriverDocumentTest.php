<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Carrier;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use App\Models\JobPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['documents.disk' => 'local']);
    }

    protected function driver(): User
    {
        $user = User::create([
            'name' => 'Jasur Driver', 'email' => 'jasur@test.com',
            'password' => Hash::make('password'), 'role' => User::ROLE_DRIVER,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        DriverProfile::create([
            'user_id' => $user->id, 'first_name' => 'Jasur', 'last_name' => 'Driver',
            'cdl_class' => 'A', 'cdl_expires_at' => now()->addYears(3), 'years_experience' => 5,
        ]);

        return $user;
    }

    /** Haqiqiy JPEG yasaydi — GD bilan qayta ishlanadigan bo'lishi kerak. */
    protected function image(): UploadedFile
    {
        $image = imagecreatetruecolor(800, 500);
        imagefilledrectangle($image, 0, 0, 800, 500, imagecolorallocate($image, 220, 230, 240));
        imagestring($image, 5, 40, 60, 'DLN T-521-8834-9901', imagecolorallocate($image, 0, 0, 0));

        $path = tempnam(sys_get_temp_dir(), 'cdl') . '.jpg';
        imagejpeg($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, 'cdl.jpg', 'image/jpeg', null, true);
    }

    public function test_a_driver_uploads_a_document_and_gets_a_watermarked_pdf()
    {
        Sanctum::actingAs($this->driver());

        $response = $this->upload([
            'type'       => 'cdl',
            'file'       => $this->image(),
            'redactions' => [
                ['x' => 0.05, 'y' => 0.1, 'w' => 0.4, 'h' => 0.1],
            ],
        ])->assertCreated();

        $document = DriverDocument::first();

        $this->assertSame('ready', $document->status);
        $this->assertSame('recruiting', $document->watermark_text);
        $this->assertNotNull($document->pdf_path);
        $this->assertCount(1, $document->redactions);

        Storage::disk('local')->assertExists($document->pdf_path);
        Storage::disk('local')->assertExists($document->original_path);

        // PDF haqiqiy PDF bo'lishi kerak.
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($document->pdf_path));

        // Fayl yo'llari hech qachon javobga chiqmasligi kerak.
        $this->assertArrayNotHasKey('original_path', $response->json('document'));
        $this->assertArrayNotHasKey('pdf_path', $response->json('document'));
    }

    public function test_redaction_coordinates_outside_zero_to_one_are_rejected()
    {
        Sanctum::actingAs($this->driver());

        $this->upload([
            'type'       => 'cdl',
            'file'       => $this->image(),
            'redactions' => [['x' => 1.4, 'y' => 0.1, 'w' => 0.4, 'h' => 0.1]],
        ])->assertStatus(422)->assertJsonValidationErrors('redactions.0.x');
    }

    public function test_only_image_uploads_are_accepted()
    {
        Sanctum::actingAs($this->driver());

        $this->upload([
            'type' => 'cdl',
            'file' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_an_unknown_document_type_is_rejected()
    {
        Sanctum::actingAs($this->driver());

        $this->upload([
            'type' => 'passport',
            'file' => $this->image(),
        ])->assertStatus(422)->assertJsonValidationErrors('type');
    }

    public function test_a_driver_can_read_their_own_pdf()
    {
        Sanctum::actingAs($this->driver());
        $this->upload(['type' => 'cdl', 'file' => $this->image()])->assertCreated();

        $this->get('/api/documents/' . DriverDocument::first()->id . '/pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_a_carrier_without_an_application_from_that_driver_cannot_read_the_pdf()
    {
        $driver = $this->driver();
        Sanctum::actingAs($driver);
        $this->upload(['type' => 'cdl', 'file' => $this->image()])->assertCreated();

        Sanctum::actingAs($this->carrier('stranger@test.com'));

        $this->get('/api/documents/' . DriverDocument::first()->id . '/pdf')->assertStatus(403);
    }

    public function test_a_carrier_the_driver_applied_to_can_read_the_pdf()
    {
        $driver = $this->driver();
        Sanctum::actingAs($driver);
        $this->upload(['type' => 'cdl', 'file' => $this->image()])->assertCreated();

        $carrierUser = $this->carrier('boss@test.com');
        $job = JobPost::create(['carrier_id' => $carrierUser->carrier->id, 'title' => 'OTR']);
        Application::create([
            'job_post_id'       => $job->id,
            'driver_profile_id' => $driver->driverProfile->id,
        ]);

        Sanctum::actingAs($carrierUser);

        $this->get('/api/documents/' . DriverDocument::first()->id . '/pdf')->assertOk();
    }

    public function test_a_driver_cannot_delete_someone_elses_document()
    {
        $owner = $this->driver();
        Sanctum::actingAs($owner);
        $this->upload(['type' => 'cdl', 'file' => $this->image()])->assertCreated();

        $other = User::create([
            'name' => 'Other', 'email' => 'other@test.com',
            'password' => Hash::make('password'), 'role' => User::ROLE_DRIVER,
        ]);
        DriverProfile::create(['user_id' => $other->id, 'first_name' => 'Other', 'last_name' => 'Driver']);

        Sanctum::actingAs($other);

        $this->deleteJson('/api/driver/documents/' . DriverDocument::first()->id)->assertStatus(403);
        $this->assertDatabaseCount('driver_documents', 1);
    }

    /** Multipart yuklash — JSON javob olish uchun Accept sarlavhasi bilan. */
    protected function upload(array $data)
    {
        return $this->post('/api/driver/documents', $data, ['Accept' => 'application/json']);
    }

    protected function carrier(string $email): User
    {
        $user = User::create([
            'name' => 'Carrier', 'email' => $email,
            'password' => Hash::make('password'), 'role' => User::ROLE_CARRIER,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        Carrier::create([
            'user_id'      => $user->id,
            'company_name' => 'Test Carrier',
        ])->forceFill([
            'fmcsa_verified_at'  => now(),
            'allowed_to_operate' => true,
        ])->save();

        return $user->fresh();
    }
}
