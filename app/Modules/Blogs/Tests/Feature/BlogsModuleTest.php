<?php

use App\Modules\Blogs\Database\Seeders\BlogsSeeder;
use App\Modules\Blogs\Models\Blog;
use App\Modules\Blogs\Models\BlogCategory;
use App\Modules\Frontend\Database\Seeders\FrontendPageSeeder;
use App\Modules\Frontend\Database\Seeders\FrontendSectionSeeder;
use App\Modules\Frontend\Database\Seeders\FrontendThemeSettingSeeder;
use App\Modules\Media\Models\Media;
use App\Modules\Shared\Support\ModuleRegistry;
use App\Modules\Shared\Support\PermissionRegistrar;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('registers the blogs module, routes, and permissions', function (): void {
    $module = app(ModuleRegistry::class)->find('blogs');
    $permissions = collect(app(PermissionRegistrar::class)->permissions())->pluck('name');

    expect($module)->not->toBeNull();
    expect(Route::has('admin.blogs.index'))->toBeTrue();
    expect(Route::has('admin.blog-categories.index'))->toBeTrue();
    expect(Route::has('admin.blog-categories.create'))->toBeFalse();
    expect(Route::has('admin.blog-categories.edit'))->toBeFalse();
    expect(Route::has('blog.index'))->toBeTrue();
    expect(Route::has('blog.show'))->toBeTrue();
    expect($permissions)->toContain(
        'blogs.view',
        'blogs.create',
        'blogs.edit',
        'blogs.delete',
        'blog-categories.view',
        'blog-categories.create',
        'blog-categories.edit',
        'blog-categories.delete'
    );
});

it('stores blog records in the module-owned blog posts table', function (): void {
    expect(Schema::hasTable('blog_categories'))->toBeTrue();
    expect(Schema::hasTable('blog_posts'))->toBeTrue();
    expect(Schema::hasTable('blogs'))->toBeFalse();
    expect((new Blog)->getTable())->toBe('blog_posts');
});

it('creates and updates blog categories through admin requests', function (): void {
    $admin = createAdminUser();

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin');

    $this->post(route('admin.blog-categories.store'), [
        'name' => 'Automation',
        'slug' => '',
        'description' => 'Automation articles.',
        'sort_order' => 1,
        'active' => 1,
    ])->assertRedirect(route('admin.blog-categories.index'));

    $category = BlogCategory::query()->where('name', 'Automation')->firstOrFail();

    expect($category->slug)->toBe('automation');
    expect($category->active)->toBeTrue();

    $this->put(route('admin.blog-categories.update', $category), [
        'name' => 'Automation Guides',
        'slug' => 'automation-playbooks',
        'description' => 'Updated category.',
        'sort_order' => 3,
        'active' => 0,
    ])->assertRedirect(route('admin.blog-categories.index'));

    $category->refresh();

    expect($category->name)->toBe('Automation Guides');
    expect($category->slug)->toBe('automation-playbooks');
    expect($category->sort_order)->toBe(3);
    expect($category->active)->toBeFalse();
});

it('validates blog category admin requests', function (): void {
    $admin = createAdminUser();

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin');

    $this->from(route('admin.blog-categories.index'))
        ->post(route('admin.blog-categories.store'), [
            'name' => '',
            'sort_order' => -1,
        ])
        ->assertRedirect(route('admin.blog-categories.index'))
        ->assertSessionHasErrors(['name', 'sort_order']);
});

it('renders blog category create and edit forms as modals on the index page', function (): void {
    $admin = createAdminUser();
    $category = BlogCategory::factory()->create([
        'name' => 'Automation',
    ]);
    Permission::findOrCreate('blog-categories.view', 'admin');
    $admin->givePermissionTo('blog-categories.view');

    $this->actingAs($admin, 'admin');

    $this->get(route('admin.blog-categories.index'))
        ->assertOk()
        ->assertSee('data-modal-open="addBlogCategoryModal"', false)
        ->assertSee('id="addBlogCategoryModal"', false)
        ->assertSee('data-modal-trigger="editBlogCategoryModal-'.$category->id.'"', false)
        ->assertSee('id="editBlogCategoryModal-'.$category->id.'"', false)
        ->assertSee('Create Category')
        ->assertSee('Update Category');
});

it('creates and updates blog posts through admin requests', function (): void {
    $admin = createAdminUser();
    $category = BlogCategory::factory()->create(['name' => 'Automation']);
    $media = Media::query()->create([
        'name' => 'Blog Thumbnail',
        'file_name' => 'blog-thumbnail.webp',
        'original_name' => 'blog-thumbnail.webp',
        'mime_type' => 'image/webp',
        'extension' => 'webp',
        'type' => 'image',
        'size' => 1024,
        'disk' => 'public',
        'path' => 'blog-thumbnail.webp',
    ]);

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin');

    $this->post(route('admin.blogs.store'), [
        'blog_category_id' => $category->id,
        'title' => 'WhatsApp Automation for Support Teams',
        'slug' => '',
        'author_name' => 'WaPro Editorial',
        'excerpt' => 'A practical automation guide.',
        'content' => '<h2>Plan the support flow</h2><p>First <strong>paragraph</strong>.</p>',
        'featured_image' => 'assets/images/sections/solutions/1.webp',
        'featured_image_media_id' => $media->id,
        'read_time_minutes' => 5,
        'sort_order' => 1,
        'status' => 'published',
        'active' => 1,
        'meta_title' => 'WhatsApp Automation for Support Teams',
        'meta_description' => 'Learn how support teams can use WhatsApp automation.',
    ])->assertRedirect(route('admin.blogs.index'));

    $blog = Blog::query()->where('title', 'WhatsApp Automation for Support Teams')->firstOrFail();

    expect($blog->slug)->toBe('whatsapp-automation-for-support-teams');
    expect($blog->blog_category_id)->toBe($category->id);
    expect($blog->category)->toBeInstanceOf(BlogCategory::class);
    expect($blog->featured_image_media_id)->toBe($media->id);
    expect($blog->safeContentHtml())->toContain('<strong>paragraph</strong>');
    expect($blog->published_at)->not->toBeNull();
    expect($blog->active)->toBeTrue();

    $this->put(route('admin.blogs.update', $blog), [
        'blog_category_id' => '',
        'title' => 'WhatsApp Automation for SaaS Support',
        'slug' => 'custom-whatsapp-support-guide',
        'author_name' => 'WaPro Team',
        'excerpt' => 'Updated guide.',
        'content' => 'Updated body.',
        'featured_image' => '',
        'read_time_minutes' => 6,
        'sort_order' => 2,
        'status' => 'draft',
        'active' => 0,
        'meta_title' => '',
        'meta_description' => '',
    ])->assertRedirect(route('admin.blogs.index'));

    $blog->refresh();

    expect($blog->title)->toBe('WhatsApp Automation for SaaS Support');
    expect($blog->blog_category_id)->toBeNull();
    expect($blog->slug)->toBe('custom-whatsapp-support-guide');
    expect($blog->status)->toBe('draft');
    expect($blog->published_at)->toBeNull();
    expect($blog->active)->toBeFalse();
});

it('validates blog admin requests', function (): void {
    $admin = createAdminUser();

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin');

    $this->from(route('admin.blogs.create'))
        ->post(route('admin.blogs.store'), [
            'title' => '',
            'author_name' => '',
            'read_time_minutes' => 0,
            'sort_order' => -1,
            'status' => 'invalid',
        ])
        ->assertRedirect(route('admin.blogs.create'))
        ->assertSessionHasErrors(['title', 'author_name', 'read_time_minutes', 'sort_order', 'status']);
});

it('keeps generated slugs unique', function (): void {
    Blog::factory()->create([
        'title' => 'Campaign Playbook',
        'slug' => 'campaign-playbook',
    ]);

    $admin = createAdminUser();

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin');

    $this->post(route('admin.blogs.store'), [
        'title' => 'Campaign Playbook',
        'slug' => '',
        'author_name' => 'WaPro Editorial',
        'excerpt' => 'Duplicate title.',
        'content' => 'Body.',
        'read_time_minutes' => 3,
        'sort_order' => 3,
        'status' => 'published',
        'active' => 1,
    ])->assertRedirect(route('admin.blogs.index'));

    expect(Blog::query()->where('slug', 'campaign-playbook-2')->exists())->toBeTrue();
});

it('includes blog seeders in the main database seeder', function (): void {
    $seeder = new class extends DatabaseSeeder
    {
        /** @var array<int, string> */
        public array $calledSeeders = [];

        /**
         * @param  array<int, class-string<Seeder>>|class-string<Seeder>  $class
         */
        public function call($class, $silent = false, array $parameters = []): static
        {
            $this->calledSeeders = is_array($class) ? $class : [$class];

            return $this;
        }
    };

    $seeder->run();

    expect($seeder->calledSeeders)->toContain(BlogsSeeder::class);
});

it('seeds dummy blog posts', function (): void {
    $this->seed(BlogsSeeder::class);

    expect(Blog::query()->count())->toBe(4);
    expect(BlogCategory::query()->count())->toBe(4);

    $blog = Blog::query()->where('slug', 'whatsapp-automation-saas-teams-reply-faster')->firstOrFail();

    expect($blog->status)->toBe('published');
    expect($blog->category?->slug)->toBe('automation');
    expect($blog->published_at)->not->toBeNull();
});

it('renders public blog pages with seo metadata and hides unpublished posts', function (): void {
    $this->seed([
        FrontendThemeSettingSeeder::class,
        FrontendSectionSeeder::class,
        FrontendPageSeeder::class,
        BlogsSeeder::class,
    ]);

    $published = Blog::query()->where('slug', 'whatsapp-automation-saas-teams-reply-faster')->firstOrFail();
    $draft = Blog::factory()->draft()->create([
        'title' => 'Draft Blog',
        'slug' => 'draft-blog',
    ]);
    $inactive = Blog::factory()->inactive()->create([
        'title' => 'Inactive Blog',
        'slug' => 'inactive-blog',
    ]);

    $this->get(route('blog.index'))
        ->assertOk()
        ->assertSee('WhatsApp marketing insights')
        ->assertSee($published->title)
        ->assertDontSee($draft->title)
        ->assertDontSee($inactive->title);

    $this->get(route('blog.show', $published))
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.route('blog.show', $published).'">', false)
        ->assertSee('<meta property="og:type" content="article">', false)
        ->assertSee('"@type":"Article"', false)
        ->assertSee($published->title);

    $this->get(route('blog.show', ['blog' => $draft->slug]))->assertNotFound();
    $this->get(route('blog.show', ['blog' => $inactive->slug]))->assertNotFound();
});
