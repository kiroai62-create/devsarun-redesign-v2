<?php
declare(strict_types=1);

/*
 * "Notion Template AI Visibility" service — inserted by upgrade v4 (app/upgrade.php)
 * when no service with this slug exists. Everything is editable later in Admin → Services.
 */
return [
    'slug' => 'notion-template-ai-visibility',
    'title' => 'Notion Template AI Visibility',
    'category' => 'growth',
    'icon' => 'sparkles',
    'tagline' => 'Get your premium Notion template recommended by ChatGPT, Claude & AI search',
    'summary' => 'For creators who sell premium Notion templates: I optimise one template so AI assistants like ChatGPT, Claude, Perplexity and Gemini can understand, find and recommend it.',
    'description' => "People now ask AI assistants things like “what is the best Notion template for freelancers?” instead of scrolling marketplaces. I rework your template's listing, public Notion page and sales page so AI search engines can understand exactly what it does, who it is for and why it is better — and are more likely to cite and recommend it.",
    'overview' => "Notion Template AI Visibility is an AEO + GEO (Answer Engine and Generative Engine Optimisation) service made specifically for people who sell premium Notion templates on Gumroad, Lemon Squeezy, Etsy, the Notion Marketplace or their own website.\n\nWhen someone asks ChatGPT, Claude, Perplexity, Gemini or Google's AI Overviews for a template recommendation, those assistants pick products they can clearly understand from public pages: a precise name, an answer-first description, real use cases, FAQs and consistent information across listings. Most template listings are written for humans scrolling a marketplace, so AI tools skip them.\n\nI research the questions buyers actually ask AI about your niche, then rewrite and restructure your template's public pages around those questions: listing copy, FAQ block, structured data on your sales page, the public Notion page itself (title, description, headings, search-indexing settings) and consistent entity details across every place the template is listed.\n\nYou get a before/after AI visibility check across the major assistants, so you can see where the template stands. Honest note: no one can guarantee that an AI will recommend a specific product — AI answers change constantly. This service maximises the chances by making your template the easiest one for AI to understand and cite.",
    'price_currency' => 'USD',
    'price_from' => 11,
    'price_suffix' => ' / template',
    'timeline' => '2–3 working days',
    'is_buyable' => 1,
    'buy_usd_cents' => 1100,
    'buy_label' => 'AI-optimise my Notion template',
    'is_featured' => 1,
    'features' => ['Optimised for ChatGPT, Claude, Perplexity & Gemini', 'AI-ready listing copy + FAQ for your template', 'Public Notion page & sales page optimisation', 'Before / after AI visibility check'],
    'ideal_for' => ['Creators selling premium Notion templates', 'Gumroad / Lemon Squeezy / Etsy template sellers', 'Notion Marketplace creators', 'Productivity & study-system creators launching a new template'],
    'benefits' => [
        ['Found where buyers now ask', 'Buyers ask AI assistants for template recommendations. Your template becomes easier for those assistants to find and cite.'],
        ['Clear, answer-first listing', 'Your listing explains what the template does, who it is for and what is inside in the format AI tools quote.'],
        ['More qualified traffic', 'People who arrive from an AI recommendation already know your template fits their need, so they buy more readily.'],
        ['Consistent everywhere', 'The same name, description and key facts across marketplaces, your site and the public Notion page.'],
        ['Better Google results too', 'Structured data, FAQs and clean headings also help classic Google search and AI Overviews.'],
        ['A clear starting report', 'A before/after check shows where your template appears today and which questions to target next.'],
    ],
    'packages' => [
        ['Per template', '$11', 'One premium Notion template', ['AI prompt research for your template niche', 'Rewritten AI-ready listing copy + FAQ', 'Public Notion page & sales page optimisation', 'Product structured data (JSON-LD) for your site', 'Before / after AI visibility check']],
    ],
    'deliverables' => [
        ['Buyer-question research', 'The real questions people ask ChatGPT, Claude and Perplexity about templates in your niche.'],
        ['AI-ready listing copy', 'Title, one-line answer, description, use cases and “what is inside”, written for marketplaces and AI.'],
        ['FAQ block', 'Clear question-and-answer pairs you can paste on Gumroad, Etsy, Notion Marketplace and your site.'],
        ['Notion page optimisation', 'Public page title, description, headings, structure and search-indexing settings.'],
        ['Structured data', 'Product + FAQ JSON-LD for your own sales/landing page (if you have one).'],
        ['AI visibility report', 'Before/after check across ChatGPT, Claude, Perplexity and Gemini with next steps.'],
    ],
    'process' => [
        ['01 / Brief', 'Template review & buyer-question research', 'Template link + where it is sold', 'Target question list'],
        ['02 / Optimise', 'Listing copy, FAQ, Notion page & schema', 'Quick review of the drafts', 'Ready-to-paste content'],
        ['03 / Publish', 'Help applying changes on each listing', 'Access or 10 minutes of your time', 'Updated, consistent listings'],
        ['04 / Report', 'Before/after AI visibility check', '—', 'Visibility report + next steps'],
    ],
    'faqs' => [
        ['Will my template definitely show up in ChatGPT or Claude?', 'No one can honestly guarantee that — AI answers change daily and every assistant decides for itself. This service makes your template the easiest one in its niche for AI to understand, trust and cite, which raises the chances of being recommended.'],
        ['What do I need to give you?', 'The template link, the pages where it is sold (Gumroad, Etsy, Notion Marketplace, your site) and a few lines about who it is for. No Notion account access is required — I can send ready-to-paste content.'],
        ['Is the $11 price per template?', 'Yes. $11 covers one template. Have several templates? Enquire and I will suggest a bundle price — every price is negotiable.'],
        ['How long until AI tools pick up the changes?', 'Search-based assistants (Perplexity, ChatGPT search, Gemini) can reflect changes within days to a few weeks after pages are re-crawled. Model knowledge updates take longer.'],
        ['Can I pay from outside India?', 'Yes. International buyers can pay $11 in USD by card; buyers in India can pay the equivalent in rupees via UPI, cards or netbanking.'],
    ],
    'not_included' => ['Designing or building the Notion template itself', 'Paid ads or marketplace fees', 'Guaranteed placement in any AI assistant'],
    'tech_stack' => ['Notion', 'ChatGPT', 'Claude', 'Perplexity', 'Gemini', 'Schema.org JSON-LD', 'Gumroad / Etsy / Notion Marketplace'],
    'keywords' => 'notion,notion template,notion templates,template seller,gumroad,etsy,notion marketplace,chatgpt,claude,perplexity,ai search,aeo,geo,template visibility,sell templates',
    'sort_order' => 95,
];
