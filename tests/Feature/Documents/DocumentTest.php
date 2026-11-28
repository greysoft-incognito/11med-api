<?php

namespace Tests\Feature\Documents;

use App\Enums\AdminPermission;
use App\Enums\Role;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\Audit\AuditLog;
use Database\Seeders\DocumentSeeder;
use Tests\Concerns\MakesAdmins;
use Tests\Fakes\RecordingAuditLog;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use MakesAdmins;

    private RecordingAuditLog $audit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->audit = new RecordingAuditLog;
        $this->app->instance(AuditLog::class, $this->audit);

        $this->seed(DocumentSeeder::class);
    }

    public function test_the_seeder_writes_every_built_in_document_once(): void
    {
        $this->assertSame(Document::BUILT_IN, Document::query()->ordered()->pluck('slug')->all());
        $this->assertSame([1, 1, 1, 1], Document::query()->ordered()->pluck('version')->all());
        $this->assertSame(4, DocumentVersion::query()->where('version', 1)->count());

        Document::query()
            ->where('slug', Document::TERMS)
            ->update(['body' => 'Written by the team.']);

        $this->seed(DocumentSeeder::class);

        $this->assertSame(4, Document::query()->count());
        $this->assertSame('Written by the team.', Document::query()->where('slug', Document::TERMS)->value('body'));
    }

    public function test_anyone_reads_a_document_by_its_slug(): void
    {
        $this->getJson('api/v1/documents/partner-terms')
            ->assertOk()
            ->assertJsonPath('data.slug', 'partner-terms')
            ->assertJsonPath('data.title', 'Terms of service')
            ->assertJsonPath('data.version', 1)
            ->assertJsonStructure(['data' => ['id', 'slug', 'title', 'body', 'version', 'updated_at']]);

        $this->getJson('api/v1/documents/about')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'DOCUMENT_NOT_FOUND');
    }

    public function test_the_team_lists_and_edits_a_document(): void
    {
        $team = $this->teamMember(AdminPermission::DocumentsEdit);
        $id = Document::query()->where('slug', Document::PRIVACY)->value('uuid');

        $this->actingAs($team)
            ->getJson('api/v1/portal/admin/documents')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.1.slug', 'privacy');

        $this->actingAs($team)
            ->putJson("api/v1/portal/admin/documents/{$id}", ['body' => "## What this covers\n\nThe new wording, long enough."])
            ->assertOk()
            ->assertJsonPath('data.title', 'Privacy policy')
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.body', "## What this covers\n\nThe new wording, long enough.")
            ->assertJsonPath('data.updated_by_name', $team->full_name);

        $this->actingAs($team)
            ->putJson("api/v1/portal/admin/documents/{$id}", ['title' => 'Our privacy policy'])
            ->assertOk()
            ->assertJsonPath('data.version', 3);

        $this->getJson('api/v1/documents/privacy')
            ->assertJsonPath('data.title', 'Our privacy policy')
            ->assertJsonPath('data.body', "## What this covers\n\nThe new wording, long enough.");

        $kept = DocumentVersion::query()
            ->whereHas('document', fn($query) => $query->where('slug', Document::PRIVACY))
            ->orderBy('version')
            ->get();

        $this->assertSame([1, 2, 3], $kept->pluck('version')->all());
        $this->assertSame('Privacy policy', $kept[1]->title);
        $this->assertSame($team->id, $kept[2]->created_by);
        $this->assertSame(['document.updated', 'document.updated'], $this->audit->actions());
    }

    public function test_a_save_that_changes_nothing_is_not_a_new_version(): void
    {
        $team = $this->teamMember(AdminPermission::DocumentsEdit);
        $document = Document::query()->where('slug', Document::TERMS)->sole();

        $this->actingAs($team)
            ->putJson("api/v1/portal/admin/documents/{$document->uuid}", ['title' => $document->title, 'body' => $document->body])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'DOCUMENT_UNCHANGED');

        $this->assertSame(1, $document->fresh()->version);
        $this->assertSame([], $this->audit->actions());
    }

    public function test_an_edit_is_validated(): void
    {
        $team = $this->teamMember(AdminPermission::DocumentsEdit);
        $id = Document::query()->where('slug', Document::TERMS)->value('uuid');

        foreach ([['title' => 'ab'], ['body' => 'Too short.'], ['title' => null]] as $body) {
            $this->actingAs($team)
                ->putJson("api/v1/portal/admin/documents/{$id}", $body)
                ->assertStatus(400)
                ->assertJsonPath('error.code', 'VALIDATION_FAILED')
                ->assertJsonPath('error.field', array_key_first($body));
        }

        $this->actingAs($team)
            ->getJson('api/v1/portal/admin/documents/0196f000-0000-7000-8000-000000000000')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'DOCUMENT_NOT_FOUND');
    }

    public function test_viewing_does_not_allow_editing_and_other_roles_are_refused(): void
    {
        $viewer = $this->teamMember(AdminPermission::DocumentsView);
        $id = Document::query()->where('slug', Document::TERMS)->value('uuid');

        $this->actingAs($viewer)->getJson("api/v1/portal/admin/documents/{$id}")->assertOk();
        $this->actingAs($viewer)->putJson("api/v1/portal/admin/documents/{$id}", ['title' => 'Terms'])->assertForbidden();
        $this->actingAs($this->teamMember(AdminPermission::SettingsEdit))->getJson('api/v1/portal/admin/documents')->assertForbidden();

        $this->actingAs(User::factory()->portal(Role::Pharmacy)->create())
            ->getJson('api/v1/portal/admin/documents')
            ->assertForbidden();
    }
}
