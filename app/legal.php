<?php
declare(strict_types=1);

/* Renders simple legal pages (privacy, terms, refunds). Content is generic — review with your advisor. */
function render_legal(string $kind): void
{
    $site = setting('site_name', 'Devs Arun');
    $email = setting('contact_email');
    $pages = [
        'privacy' => ['Privacy Policy', [
            ['What we collect', "When you submit a form, chat with the assistant or make a payment, we collect the details you provide (name, email, phone, company, project information and files you attach). We also record basic, first-party analytics: pages visited, referrer, device type and browser. IP addresses are stored only as one-way hashes."],
            ['How we use it', "Only to respond to your enquiry, prepare quotes, deliver projects, send receipts and improve this website. We never sell your data."],
            ['Payments', "Payments are processed by Razorpay. Card / UPI / bank details are entered on Razorpay's secure checkout and never stored on our servers."],
            ['AI assistant', "Chat messages are stored so we can follow up on your enquiry. If an AI provider is enabled, messages are sent to that provider (for example Google Gemini or OpenAI) to generate replies."],
            ['Cookies', "We use a session cookie (security, forms) and a first-party visitor cookie for anonymous analytics. No third-party advertising trackers."],
            ['Your rights', "Email {$email} to access, correct or delete your data."],
        ]],
        'terms' => ['Terms of Service', [
            ['Quotes & pricing', "All listed prices are starting prices and are negotiable. The final scope, price and timeline are confirmed in writing before work begins."],
            ['Payments', "Projects are milestone-based (typically an advance plus balance on delivery). Online bookings are charged at the displayed amount via Razorpay."],
            ['Client responsibilities', "You provide content, access and timely feedback. Delays in inputs may shift timelines."],
            ['Ownership', "On full payment, you own the final deliverables built for you. Third-party tools, libraries and services remain under their own licences."],
            ['Third-party costs', "Domain, hosting, API usage, store developer fees and paid plugins are not included unless stated."],
            ['No guarantees', "SEO, AEO and GEO work improves signals but cannot guarantee rankings, AI mentions, traffic, leads or revenue."],
            ['Contact', "Questions: {$email}."],
        ]],
        'refund-policy' => ['Refund & Cancellation Policy', [
            ['Before work starts', "If you cancel before work begins, the advance is refunded in full (minus payment gateway charges) within 5–7 working days."],
            ['After work starts', "Completed milestones are non-refundable. Unused portions of an advance may be refunded on a pro-rata basis."],
            ['Online bookings', "For services booked online, request cancellation within 48 hours of payment for a full refund if work has not started."],
            ['How to request', "Email {$email} with your order number. Approved refunds go back to the original payment method via Razorpay."],
        ]],
    ];
    [$title, $sections] = $pages[$kind];
    page_start(['title' => $title]);
    ?>
    <section class="pt-32 sm:pt-40"><div class="mx-auto max-w-3xl px-5 sm:px-6">
      <p class="kicker">// legal</p><h1 class="mt-4 text-4xl font-extrabold tracking-tight"><?= e($title) ?></h1>
      <p class="mt-3 text-sm text-white/40"><?= e($site) ?> · Last updated <?= date('F Y') ?></p>
      <div class="prose-da mt-10"><?php foreach ($sections as [$h, $p]): ?><h2><?= e($h) ?></h2><p><?= e($p) ?></p><?php endforeach; ?></div>
    </div></section>
    <?php
    page_end();
}
