<?php

namespace App\Modules\Commerce\Database\Seeders;

use App\Modules\Commerce\Storefront\Models\StorefrontContent;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Seeder;

class StorefrontBlogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Workspace::query()->pluck('id') as $workspaceId) {
            $this->seedWorkspace((int) $workspaceId);
        }
    }

    /**
     * Upserts the demo articles for one store. The storefront lists posts by
     * creation date, so created_at is aligned with the publish date.
     */
    public function seedWorkspace(int $workspaceId): void
    {
        foreach ($this->posts() as $post) {
            $publishedAt = now()->subDays($post['days_ago'])->setTime(9, 30);

            $record = StorefrontContent::query()->firstOrNew([
                'workspace_id' => $workspaceId,
                'type' => 'blog',
                'slug' => $post['slug'],
            ]);
            $record->forceFill([
                'data' => [
                    'title' => $post['title'],
                    'category' => $post['category'],
                    'excerpt' => $post['excerpt'],
                    'cover_image' => $post['cover_image'],
                    'content' => $post['content'],
                ],
                'published_at' => $publishedAt,
                'created_at' => $publishedAt,
            ])->save();
        }
    }

    /**
     * @return list<array{slug: string, title: string, category: string, excerpt: string, cover_image: string, days_ago: int, content: string}>
     */
    private function posts(): array
    {
        return [
            [
                'slug' => 'guide-to-heavyweight-streetwear',
                'title' => 'The Ultimate Guide to Heavyweight Streetwear: Why Fabric GSM Matters',
                'category' => 'Fabric & Quality',
                'excerpt' => 'GSM is the single most useful number on a garment spec sheet. Here is what it measures, which weights suit tees, sweatshirts and hoodies, and how to tell quality fabric from a heavy-but-cheap one.',
                'cover_image' => '/storage/commerce-demo/v1/hoodie/grey/detail.webp',
                'days_ago' => 3,
                'content' => <<<'HTML'
<p>Pick up two plain black t-shirts in a shop and you can usually feel the difference before you read the label. One drapes and holds its shape; the other clings, twists at the side seams and turns slightly see-through under bright light. Most of that difference comes down to one number: <strong>GSM</strong>.</p>

<h2>What GSM actually measures</h2>
<p>GSM stands for <em>grams per square metre</em>. It is the weight of one square metre of fabric, and it is the standard way mills and factories describe fabric density around the world. A higher GSM means more fibre packed into the same area, which generally gives a thicker, more opaque and more structured garment.</p>
<p>It is worth remembering what GSM does <em>not</em> tell you. It says nothing about the fibre itself, how the yarn was spun or how the fabric was finished. A heavy fabric made from short, carded cotton can still pill and feel rough. Weight is the starting point, not the whole story.</p>

<h2>Recommended weights by garment</h2>
<p>These are the ranges we work with on our own production line and the ones most premium streetwear labels use:</p>
<table>
  <thead>
    <tr><th>Garment</th><th>Lightweight</th><th>Midweight</th><th>Heavyweight</th></tr>
  </thead>
  <tbody>
    <tr><td>T-shirt</td><td>140–160 GSM</td><td>180–200 GSM</td><td>220–260 GSM</td></tr>
    <tr><td>Polo</td><td>180 GSM</td><td>200–220 GSM</td><td>240 GSM</td></tr>
    <tr><td>Crewneck sweatshirt</td><td>280 GSM</td><td>320–350 GSM</td><td>380–420 GSM</td></tr>
    <tr><td>Hoodie</td><td>300 GSM</td><td>350–380 GSM</td><td>400–480 GSM</td></tr>
  </tbody>
</table>
<p>For a boxy, oversized tee that keeps its shape, 220 GSM is a sensible minimum. For a hoodie that feels substantial without becoming too warm indoors, 400 GSM is a good balance.</p>

<figure>
  <img src="/storage/commerce-demo/v1/oversized-tee/black/detail.webp" alt="Close-up of the collar and shoulder of a heavyweight black t-shirt">
  <figcaption>A 240 GSM combed cotton tee: dense enough to stay opaque, with a rib collar that holds its shape.</figcaption>
</figure>

<h2>Why heavyweight cotton looks better for longer</h2>
<h3>It holds its silhouette</h3>
<p>Dense jersey has more body, so it falls away from the torso in clean lines instead of clinging. That is what gives oversized and boxy fits their relaxed, architectural look.</p>
<h3>Collars stay flat</h3>
<p>The wavy, stretched-out collar often called "bacon neck" happens when a thin rib loses its recovery. A heavier 1x1 rib, ideally with a little elastane and twin-needle stitching, keeps its round shape for many more washes.</p>
<h3>Colour stays deeper</h3>
<p>More fibre means more dye uptake. Black, navy and olive look richer on heavyweight fabric and fade more evenly over time.</p>

<h2>French terry or brushed fleece?</h2>
<p>For sweatshirts and hoodies, the knit structure matters as much as the weight. The two common options are:</p>
<ul>
  <li><strong>Loopback French terry:</strong> small loops on the inside, left unbrushed. Breathable, quick to dry and good for most of the year.</li>
  <li><strong>Brushed fleece:</strong> the loops are brushed into a soft, fluffy pile. Warmer and cosier, but it can shed and pill more if the yarn quality is low.</li>
</ul>
<p>Both can be excellent. The deciding factor is usually climate and how the garment will be worn.</p>

<h2>How to judge quality beyond the number</h2>
<ol>
  <li><strong>Check the cotton.</strong> Combed or ring-spun cotton has longer, smoother fibres than carded open-end yarn. It feels softer and pills less.</li>
  <li><strong>Look for pre-shrinking.</strong> Fabric that has been compacted or pre-shrunk at the mill will stay much closer to its original size after washing.</li>
  <li><strong>Inspect the seams.</strong> Taped shoulder seams, twin-needle hems and even stitch density are signs of careful construction.</li>
  <li><strong>Hold it up to the light.</strong> A good heavyweight tee should be almost fully opaque, even in white.</li>
</ol>

<blockquote>A heavier fabric is not automatically a better one. The best garments combine the right weight with good fibre, careful finishing and solid construction.</blockquote>

<h2>The bottom line</h2>
<p>When you compare blanks or finished garments, start with GSM to narrow the field, then look at fibre, finishing and stitching. If you are choosing a base for your own label, order a mixed sample pack in two or three weights and wear them for a week. How a garment feels after a few washes will tell you more than any spec sheet.</p>
HTML,
            ],
            [
                'slug' => 'factory-to-retail-wholesale-model',
                'title' => 'Direct From Factory: How Custom Garment Packs Help Independent Brands Grow',
                'category' => 'Wholesale',
                'excerpt' => 'High minimum order quantities have kept many good clothing ideas from launching. Here is how small, mixed packs bought directly from the factory lower the risk of starting and scaling a label.',
                'cover_image' => '/storage/commerce-demo/v1/hoodie/olive/lifestyle.webp',
                'days_ago' => 9,
                'content' => <<<'HTML'
<p>Ask anyone who has tried to start a clothing brand what the hardest part was, and many will give the same answer: the first order. Traditional manufacturers often require hundreds of units per style and colour before they will start production. For a new label, that means tying up cash in stock that has not been tested with a single customer.</p>

<h2>The problem with high minimums</h2>
<p>Minimum order quantities (MOQs) exist for good reasons. Setting up a cutting table, dyeing a batch of fabric and adjusting machines all cost the same whether a factory makes fifty pieces or five thousand. Large runs spread that cost thin.</p>
<p>But for small brands, high MOQs create real problems:</p>
<ul>
  <li><strong>Cash gets locked in inventory.</strong> Money spent on unsold sizes cannot be spent on marketing or new designs.</li>
  <li><strong>Size curves are guesswork.</strong> Order too many XLs and too few mediums and you are left with dead stock.</li>
  <li><strong>Testing is expensive.</strong> Trying a new colour or fit means committing to another full run.</li>
</ul>

<h2>How mixed packs change the maths</h2>
<p>A custom pack is a small, pre-defined bundle that you configure yourself: choose the product, then split the units across sizes and colours. Our packs start at 12 pieces, and because we cut and sew in-house, there is no distributor adding a margin between the factory and you.</p>

<figure>
  <img src="/storage/commerce-demo/v1/polo/beige/detail.webp" alt="Dark polo shirt laid flat, showing the two-button placket">
  <figcaption>Mix colours and sizes inside a single pack to test what your customers actually buy.</figcaption>
</figure>

<h3>A worked example</h3>
<p>Say you want to launch a heavyweight hoodie in two colours. With a traditional 300-unit MOQ per colour, you would need 600 units before selling one. With mixed packs, a sensible first order could look like this:</p>
<table>
  <thead>
    <tr><th>Colour</th><th>S</th><th>M</th><th>L</th><th>XL</th><th>Total</th></tr>
  </thead>
  <tbody>
    <tr><td>Olive</td><td>2</td><td>4</td><td>4</td><td>2</td><td>12</td></tr>
    <tr><td>Black</td><td>2</td><td>4</td><td>4</td><td>2</td><td>12</td></tr>
  </tbody>
</table>
<p>Twenty-four pieces is enough to photograph, sell through a launch and learn which colour and sizes move fastest. Your second order is then based on real sales data instead of guesses.</p>

<h2>Five tips for your first wholesale order</h2>
<ol>
  <li><strong>Order samples first.</strong> Check the fabric, fit and finishing yourself before committing to stock.</li>
  <li><strong>Weight your size curve to the middle.</strong> For most adult streetwear, M and L sell fastest. Keep S and XL lighter until you know your audience.</li>
  <li><strong>Start with two colours.</strong> One core neutral (black, grey or white) and one seasonal colour gives choice without spreading stock thin.</li>
  <li><strong>Plan your reorder point.</strong> Decide in advance how many units sold will trigger the next pack, so you never run out of best sellers.</li>
  <li><strong>Factor in shipping.</strong> Heavier garments cost more to ship. Ask for volumetric estimates before you set your retail price.</li>
</ol>

<blockquote>Buying in small, mixed packs turns your first collection into an experiment you can afford to run, and your second collection into a decision backed by data.</blockquote>

<h2>Scaling up when you are ready</h2>
<p>Packs are not only for launches. Many established brands use them to test new colourways or to restock best sellers between larger production runs. When a style proves itself, moving to larger volumes brings the unit price down further while the pack model stays available for experiments.</p>
<p>If you are planning your first drop, browse our wholesale packs or contact our team for help choosing weights, colours and a size split that suits your market.</p>
HTML,
            ],
            [
                'slug' => 'garment-care-washing-guide',
                'title' => 'Streetwear Care Guide: How to Wash, Dry and Store Premium Cotton',
                'category' => 'Care Guide',
                'excerpt' => 'A good hoodie or heavyweight tee can last for years with the right care. Follow these simple washing, drying and storage habits to prevent shrinkage, fading and pilling.',
                'cover_image' => '/storage/commerce-demo/v1/crewneck/olive/detail.webp',
                'days_ago' => 16,
                'content' => <<<'HTML'
<p>Premium cotton is an investment, and like most good things it rewards a little care. The good news is that looking after heavyweight tees, sweatshirts and hoodies does not take more time. It mostly means changing a few default settings on your washing machine.</p>

<h2>Before you wash</h2>
<ul>
  <li><strong>Read the care label.</strong> It is specific to the fabric and dyes used in that garment.</li>
  <li><strong>Turn garments inside out.</strong> This protects prints, embroidery and the outer surface from abrasion.</li>
  <li><strong>Sort by colour and weight.</strong> Wash darks with darks, and keep heavy items like jeans away from soft fleece.</li>
  <li><strong>Close zips and fasten buttons.</strong> Open metal teeth can snag knitted fabric.</li>
</ul>

<h2>Washing: cold and gentle</h2>
<p>Most shrinkage and fading is caused by heat and agitation, not by the wash itself.</p>
<ol>
  <li><strong>Use cold or cool water,</strong> no more than 30°C (86°F). Modern detergents clean well at low temperatures.</li>
  <li><strong>Choose a gentle or delicate cycle</strong> with a lower spin speed.</li>
  <li><strong>Use less detergent than you think.</strong> Excess detergent stays in the fibres and makes cotton feel stiff.</li>
  <li><strong>Skip bleach and fabric softener.</strong> Bleach weakens fibres, and softener coats them, reducing breathability.</li>
  <li><strong>Do not overload the drum.</strong> Garments need room to move to get clean without rubbing hard against each other.</li>
</ol>

<figure>
  <img src="/storage/commerce-demo/v1/hoodie/white/detail.webp" alt="Close-up of a white hoodie showing the drawcord and kangaroo pocket">
  <figcaption>Light colours stay bright longer when washed cold and dried away from direct sunlight.</figcaption>
</figure>

<h2>Drying: the step that matters most</h2>
<p>Tumble dryers are the biggest cause of shrinkage in cotton. High heat tightens the fibres and can take a garment down a full size.</p>
<ul>
  <li><strong>Lay knitwear flat to dry.</strong> Hanging a wet hoodie can stretch the shoulders and hem.</li>
  <li><strong>Reshape while damp.</strong> Gently pull seams straight and smooth the collar and cuffs.</li>
  <li><strong>Dry out of direct sunlight</strong> to avoid uneven fading, especially on dark colours.</li>
  <li><strong>If you must tumble dry,</strong> use the lowest heat setting and remove garments while they are still slightly damp.</li>
</ul>

<h2>Dealing with common problems</h2>
<h3>Pilling</h3>
<p>Small balls of fibre can form where fabric rubs, such as under the arms. Remove them with a fabric shaver or a sweater comb, and wash inside out to reduce friction.</p>
<h3>Faded blacks</h3>
<p>Wash dark garments inside out in cold water with a detergent made for dark colours. Fewer washes also help: airing a hoodie between wears is often enough.</p>
<h3>Stretched collars</h3>
<p>Avoid pulling tees on and off by the collar. If a rib has relaxed, a cool wash and flat dry can help it recover some shape.</p>

<h2>Storing your garments</h2>
<ul>
  <li><strong>Fold heavy knitwear</strong> instead of hanging it, so gravity does not stretch the shoulders.</li>
  <li><strong>Keep garments clean and dry</strong> before storing them for a season.</li>
  <li><strong>Use breathable storage</strong> such as cotton bags rather than sealed plastic.</li>
</ul>

<blockquote>Wash less, wash cold and skip the dryer. Those three habits do more for the life of your clothes than anything else.</blockquote>

<h2>Quick reference</h2>
<table>
  <thead>
    <tr><th>Garment</th><th>Wash</th><th>Dry</th></tr>
  </thead>
  <tbody>
    <tr><td>T-shirts and polos</td><td>Cold, gentle, inside out</td><td>Hang or lay flat</td></tr>
    <tr><td>Hoodies and sweatshirts</td><td>Cold, gentle, inside out</td><td>Lay flat</td></tr>
    <tr><td>Denim</td><td>Cold, inside out, infrequently</td><td>Hang to dry</td></tr>
  </tbody>
</table>
HTML,
            ],
            [
                'slug' => 'oversized-fit-size-guide',
                'title' => 'How to Find Your Perfect Oversized Fit: A Practical Size Guide',
                'category' => 'Style & Fit',
                'excerpt' => 'Oversized does not simply mean buying a size up. Learn how to measure yourself, read a size chart and choose the right fit for tees, sweatshirts and hoodies.',
                'cover_image' => '/storage/commerce-demo/v1/oversized-tee/white/lifestyle.webp',
                'days_ago' => 24,
                'content' => <<<'HTML'
<p>Oversized silhouettes have become a staple of modern streetwear, but the look only works when the proportions are right. Too small and it reads as a regular fit; too big and it swamps your frame. The secret is understanding which measurements matter.</p>

<h2>Oversized is a cut, not just a bigger size</h2>
<p>A garment designed to be oversized is cut differently from a regular one. Typically it has:</p>
<ul>
  <li><strong>Dropped shoulders</strong> that sit below the natural shoulder line.</li>
  <li><strong>A wider body</strong> with extra room through the chest.</li>
  <li><strong>Wider, slightly shorter sleeves</strong> relative to the body width.</li>
  <li><strong>A boxier length</strong> that sits around the hip rather than covering it.</li>
</ul>
<p>That is why simply sizing up in a regular fit rarely looks the same. The sleeves get too long and the hem drops too low before the body feels relaxed.</p>

<h2>How to measure yourself</h2>
<p>You only need a soft tape measure and a few minutes. Measure over a thin t-shirt and keep the tape snug but not tight.</p>
<ol>
  <li><strong>Chest:</strong> around the fullest part of your chest, under your arms.</li>
  <li><strong>Shoulders:</strong> across your back from one shoulder point to the other.</li>
  <li><strong>Length:</strong> from the highest point of your shoulder down to where you want the hem to fall.</li>
</ol>

<h2>Reading a garment size chart</h2>
<p>Most brands, including us, list <strong>garment measurements</strong>, which are the measurements of the clothing laid flat, not your body. A chest width of 60 cm means 120 cm all the way around.</p>
<p>A simple way to choose:</p>
<ul>
  <li>Take your favourite tee or hoodie, lay it flat and measure its chest width and length.</li>
  <li>Compare those numbers with the size chart and pick the closest match.</li>
  <li>If you are between sizes, size down for a cleaner oversized look or up for a more relaxed drape.</li>
</ul>

<figure>
  <img src="/storage/commerce-demo/v1/hoodie/olive/lifestyle.webp" alt="Model wearing an olive oversized hoodie with straight-leg jeans and white trainers">
  <figcaption>An oversized hoodie should sit just below the waistband with sleeves that stack slightly at the wrist.</figcaption>
</figure>

<h2>What a good oversized fit looks like</h2>
<h3>T-shirts</h3>
<p>The shoulder seam sits a few centimetres below your shoulder, the sleeve reaches mid-bicep to elbow, and the hem lands around the top of your hips.</p>
<h3>Sweatshirts and hoodies</h3>
<p>The body has room to move without billowing, cuffs gather slightly at the wrist, and the ribbed hem sits just below your waistband.</p>

<h2>Styling oversized pieces</h2>
<ul>
  <li><strong>Balance volume.</strong> Pair an oversized top with straight or tapered trousers rather than very wide ones.</li>
  <li><strong>Mind the length.</strong> A slight front tuck can define your waist without losing the relaxed shape.</li>
  <li><strong>Layer thoughtfully.</strong> A heavyweight tee under an open overshirt adds depth while keeping the silhouette clean.</li>
</ul>

<blockquote>The best fit guide is a garment you already love. Measure it, compare it and you will rarely order the wrong size.</blockquote>

<h2>Still unsure?</h2>
<p>Every product page includes a detailed size chart with garment measurements. If you are ordering for a store or ordering in bulk, ask our team for a sample pack in two sizes so you can compare the fit in person before placing a larger order.</p>
HTML,
            ],
            [
                'slug' => 'capsule-wardrobe-streetwear-essentials',
                'title' => 'Building a Streetwear Capsule Wardrobe With Seven Essentials',
                'category' => 'Style & Fit',
                'excerpt' => 'Fewer, better pieces that work together. Here is how to build a versatile streetwear capsule wardrobe that covers most occasions without filling your closet.',
                'cover_image' => '/storage/home-sections/content-grid/content-grid-3.jpg',
                'days_ago' => 33,
                'content' => <<<'HTML'
<p>A capsule wardrobe is a small collection of versatile pieces that work well together. Instead of chasing every trend, you invest in essentials that can be combined in dozens of ways. It saves time each morning, saves money over the year and tends to make your style more recognisable.</p>

<h2>Start with a colour palette</h2>
<p>The easiest way to make every piece work with every other piece is to keep the palette tight. Choose:</p>
<ul>
  <li><strong>Two or three neutrals</strong> such as black, white, grey, navy or beige.</li>
  <li><strong>One or two accent colours</strong> such as olive, burgundy or washed blue.</li>
</ul>
<p>With a palette like this, almost any top will work with almost any bottom.</p>

<h2>The seven essentials</h2>
<ol>
  <li><strong>Heavyweight white tee.</strong> The foundation of most outfits. Choose a 220–260 GSM fabric so it stays opaque and holds its shape.</li>
  <li><strong>Heavyweight black or navy tee.</strong> A darker base for evenings and layering.</li>
  <li><strong>Grey crewneck sweatshirt.</strong> Works over a collared shirt, under a jacket or on its own.</li>
  <li><strong>Neutral hoodie.</strong> Olive, black or grey in a midweight to heavyweight fleece.</li>
  <li><strong>Straight-leg jeans.</strong> A mid or dark wash that can be dressed up or down.</li>
  <li><strong>A versatile jacket.</strong> A leather, denim or utility jacket adds structure to simple layers.</li>
  <li><strong>Clean trainers.</strong> White or neutral leather trainers go with everything above.</li>
</ol>

<figure>
  <img src="/storage/categories/mens-jackets.jpg" alt="Black leather jacket on a hanger against a white background">
  <figcaption>One well-made jacket can change the feel of every outfit in your capsule.</figcaption>
</figure>

<h2>Outfit formulas that always work</h2>
<table>
  <thead>
    <tr><th>Occasion</th><th>Formula</th></tr>
  </thead>
  <tbody>
    <tr><td>Everyday</td><td>White tee + straight jeans + trainers</td></tr>
    <tr><td>Cool weather</td><td>Hoodie + jacket + dark jeans</td></tr>
    <tr><td>Smart casual</td><td>Black tee + leather jacket + dark jeans</td></tr>
    <tr><td>Weekend</td><td>Crewneck + relaxed jeans + trainers</td></tr>
  </tbody>
</table>

<h2>Quality over quantity</h2>
<p>A capsule wardrobe only works if the pieces last. When you are wearing the same seven items regularly, construction matters:</p>
<ul>
  <li>Look for combed or ring-spun cotton that resists pilling.</li>
  <li>Choose pre-shrunk fabrics so the fit stays consistent.</li>
  <li>Check for reinforced seams at the shoulders and hems.</li>
</ul>

<blockquote>A smaller wardrobe of better clothes is easier to wear, easier to care for and easier on the planet.</blockquote>

<h2>Growing your capsule over time</h2>
<p>Once the essentials are in place, add one piece at a time and ask a simple question: does it work with at least three things I already own? If the answer is yes, it belongs in your capsule. If not, it is probably an impulse buy.</p>
<p>Start with the basics, wear them often and replace them only when they are genuinely worn out. That is the quiet power of a capsule wardrobe.</p>
HTML,
            ],
            [
                'slug' => 'denim-guide-raw-rinsed-washed',
                'title' => 'Denim 101: Raw, Rinsed and Washed Jeans Explained',
                'category' => 'Fabric & Quality',
                'excerpt' => 'What is the difference between raw, rinsed and washed denim, and which should you choose? A clear guide to denim weights, washes and fits.',
                'cover_image' => '/storage/commerce-demo/v1/jeans/navy/lifestyle.webp',
                'days_ago' => 41,
                'content' => <<<'HTML'
<p>Jeans are one of the few garments almost everyone owns, yet the terms used to describe them can be confusing. Raw, rinsed, washed, selvedge, ounce weight: here is what each one means and how it affects the jeans you wear.</p>

<h2>How denim is made</h2>
<p>Denim is a sturdy cotton twill. The lengthwise threads (the warp) are dyed, traditionally with indigo, while the crosswise threads (the weft) are left undyed. That is why the outside of most jeans is blue and the inside is lighter.</p>
<p>Indigo sits on the surface of the yarn rather than soaking all the way through. As the fabric is worn and washed, the surface dye wears away and reveals the white core. This is what creates fading.</p>

<h2>Raw, rinsed and washed</h2>
<h3>Raw denim</h3>
<p>Raw (or dry) denim has not been washed after dyeing. It feels stiff at first and is very dark. Over months of wear it softens and develops fades that follow how you move, such as creases behind the knees and whiskers at the hips.</p>
<ul>
  <li><strong>Pros:</strong> personal fades, deep colour, long-lasting.</li>
  <li><strong>Cons:</strong> stiff break-in period, may shrink if unsanforised, indigo can rub off onto light surfaces.</li>
</ul>
<h3>Rinsed denim</h3>
<p>Rinsed denim has been washed once to remove excess dye and soften the fabric slightly. It keeps a deep, even colour but is more comfortable from day one and will not shrink much further.</p>
<h3>Washed denim</h3>
<p>Washed denim has been through additional processes such as stone washing or enzyme washing to create a lighter, softer, lived-in look straight away.</p>

<figure>
  <img src="/storage/commerce-demo/v1/jeans/grey/lifestyle.webp" alt="Pair of grey straight-leg jeans laid flat">
  <figcaption>Washed denim gives a soft, broken-in feel from the first wear.</figcaption>
</figure>

<h2>Understanding denim weight</h2>
<p>Denim weight is usually given in ounces per square yard.</p>
<table>
  <thead>
    <tr><th>Weight</th><th>Range</th><th>Best for</th></tr>
  </thead>
  <tbody>
    <tr><td>Lightweight</td><td>Under 12 oz</td><td>Warm climates, summer</td></tr>
    <tr><td>Midweight</td><td>12–16 oz</td><td>Year-round everyday wear</td></tr>
    <tr><td>Heavyweight</td><td>Over 16 oz</td><td>Cold weather, enthusiasts</td></tr>
  </tbody>
</table>
<p>For most people, a 12–14 oz midweight denim offers the best balance of durability and comfort.</p>

<h2>Choosing a fit</h2>
<ul>
  <li><strong>Slim:</strong> close through the thigh and leg, a modern, streamlined look.</li>
  <li><strong>Straight:</strong> the same width from knee to hem. Timeless and easy to style.</li>
  <li><strong>Relaxed:</strong> more room through the seat and thigh, pairs well with oversized tops.</li>
  <li><strong>Tapered:</strong> roomy at the top and narrowing towards the ankle.</li>
</ul>

<h2>Caring for your jeans</h2>
<ol>
  <li>Wash infrequently, only when they are actually dirty.</li>
  <li>Turn them inside out and wash in cold water.</li>
  <li>Hang to dry instead of tumble drying.</li>
  <li>For raw denim, wait as long as is comfortable before the first wash to get sharper fades.</li>
</ol>

<blockquote>There is no single best pair of jeans. There is only the right combination of fabric, wash and fit for how you live.</blockquote>

<h2>Which should you choose?</h2>
<p>If you enjoy watching clothes evolve with you, try raw denim. If you want a sharp, dark pair that is comfortable now, go for rinsed. If you prefer a soft, relaxed look straight away, washed denim is the easiest choice. Whichever you pick, a well-made pair will only get better with time.</p>
HTML,
            ],
        ];
    }
}
