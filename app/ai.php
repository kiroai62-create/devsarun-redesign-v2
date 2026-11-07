<?php
declare(strict_types=1);

/*
 * AI assistant: knowledge built from the live services table + admin
 * "knowledge base" setting. Uses OpenAI / Gemini / any OpenAI-compatible
 * API (Groq, OpenRouter…). Falls back to a built-in rules engine so the
 * assistant works even without an API key.
 */

function ai_knowledge(): string
{
    $lines = [];
    foreach (services_active() as $s) {
        $pk = implode('; ', array_map(static fn($p) => $p['name'] . ' ' . $p['price'], service_packages($s)));
        $lines[] = sprintf('- %s (%s): %s | Price: %s (negotiable) | Packages: %s | Timeline: %s | Details page: /services/%s/ | Brochure: /brochure/%s/',
            $s['title'], $s['category'], $s['summary'], price_text($s), $pk ?: 'custom', $s['timeline'] ?: 'shared with quote', $s['slug'], $s['slug']);
    }
    $site = setting('site_name', 'Devs Arun');
    $owner = setting('owner_name', 'Arun');
    $kb = setting('ai_knowledge', '');
    return "You are the friendly AI assistant on {$site}'s website ({$owner}, an independent full-stack developer & digital solutions studio based in India, working with clients worldwide).\n"
        . "GOALS: answer questions about services, pricing, process and past work; help the visitor pick the right service; and guide serious visitors to fill the project brief at /start-project/ (or book a free call). Ask for their name + email/WhatsApp when they show buying intent.\n"
        . "RULES:\n"
        . "1. EVERY price is a starting point and is NEGOTIABLE. Nothing is fixed. Always mention this when talking about money. Never refuse due to budget — suggest a smaller scope instead.\n"
        . "2. Never invent services, guarantees, client names or numbers that are not listed below. AEO/GEO has no guaranteed rankings, AI mentions or leads.\n"
        . "3. Keep replies short (2–5 sentences or a tight bullet list), warm and professional. Reply in the visitor's language (English, Hindi or Hinglish).\n"
        . "4. You may use **bold** and markdown links like [Start a project](/start-project/). Only link to pages on this site listed here: /services/, /services/<slug>/, /services/<slug>/#enquire, /brochure/<slug>/, /work/, /pricing/, /aeo-geo/, /start-project/, /contact/, /track/.\n"
        . "5. Payment: milestone based (advance + rest on delivery). Some services can be booked online via Razorpay on their service page; negotiated quotes get a secure payment link.\n"
        . "Contact: email " . setting('contact_email', 'itsdevsarun@gmail.com') . (setting('whatsapp') !== '' ? ', WhatsApp ' . setting('whatsapp') : '') . ". Response time: within 24 hours.\n\n"
        . "SERVICES:\n" . implode("\n", $lines)
        . ($kb !== '' ? "\n\nEXTRA KNOWLEDGE FROM THE OWNER:\n" . $kb : '');
}

function ai_configured(): bool
{
    return setting('ai_enabled', '1') === '1' && setting('ai_api_key') !== '';
}

/** @param array<int,array{role:string,content:string}> $history */
function ai_reply(array $history): array
{
    if (ai_configured()) {
        try {
            $text = ai_call_provider($history);
            if (trim($text) !== '') {
                return ['text' => trim($text), 'engine' => setting('ai_provider', 'openai')];
            }
        } catch (Throwable $e) {
            error_log('AI provider error: ' . $e->getMessage());
        }
    }
    $last = '';
    for ($i = count($history) - 1; $i >= 0; $i--) {
        if ($history[$i]['role'] === 'user') {
            $last = $history[$i]['content'];
            break;
        }
    }
    return ['text' => ai_rules_reply($last), 'engine' => 'rules'];
}

function ai_http(string $url, array $payload, array $headers): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false || $code >= 400) {
        throw new RuntimeException('AI HTTP ' . $code . ': ' . mb_substr((string)$resp, 0, 300));
    }
    return json_decode((string)$resp, true) ?: [];
}

function ai_call_provider(array $history): string
{
    $provider = setting('ai_provider', 'openai');
    $key = setting('ai_api_key');
    $system = ai_knowledge();
    $history = array_slice($history, -14);

    if ($provider === 'gemini') {
        $model = setting('ai_model', 'gemini-2.0-flash');
        $contents = [];
        foreach ($history as $m) {
            $contents[] = ['role' => $m['role'] === 'assistant' ? 'model' : 'user', 'parts' => [['text' => $m['content']]]];
        }
        $data = ai_http('https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent', [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => $contents,
            'generationConfig' => ['temperature' => 0.5, 'maxOutputTokens' => 500],
        ], ['x-goog-api-key: ' . $key]);
        return (string)($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
    }

    // OpenAI or any OpenAI-compatible endpoint (Groq, OpenRouter, Together…)
    $base = $provider === 'openai' ? 'https://api.openai.com/v1' : rtrim(setting('ai_base_url', 'https://api.groq.com/openai/v1'), '/');
    $model = setting('ai_model', $provider === 'openai' ? 'gpt-4o-mini' : 'llama-3.3-70b-versatile');
    $messages = [['role' => 'system', 'content' => $system]];
    foreach ($history as $m) {
        $messages[] = ['role' => $m['role'] === 'assistant' ? 'assistant' : 'user', 'content' => $m['content']];
    }
    $data = ai_http($base . '/chat/completions', ['model' => $model, 'messages' => $messages, 'temperature' => 0.5, 'max_tokens' => 500], ['Authorization: Bearer ' . $key]);
    return (string)($data['choices'][0]['message']['content'] ?? '');
}

/** Offline assistant: intent + service keyword matching. */
function ai_rules_reply(string $msg): string
{
    $m = mb_strtolower($msg);
    $has = static function (array $words) use ($m): bool {
        foreach ($words as $w) {
            $w = trim($w);
            $hit = mb_strlen($w) <= 4 ? (bool)preg_match('/(?<![a-z0-9])' . preg_quote($w, '/') . '/u', $m) : str_contains($m, $w);
            if ($hit) {
                return true;
            }
        }
        return false;
    };

    if (preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $msg) || preg_match('/\+?\d[\d\s\-]{8,}/', $msg)) {
        return "Thank you! 🙏 I've saved your contact details and Arun will reach out within 24 hours.\n\nTo get an exact (negotiable) quote faster, you can also fill the 2-minute [project brief](/start-project/).";
    }
    if ($has(['start a project', 'start project', 'hire', 'get started', 'shuru'])) {
        return "Great — let's do it! 🚀 The quickest way is the 2-minute [project brief](/start-project/): pick a service, describe your idea, choose a budget range (it's **negotiable**) and you'll get a reference ID plus a quote within 24 hours.\n\nOr simply share your name and WhatsApp number here.";
    }
    if ($has(['share my whatsapp', 'my number', 'call me', 'contact me'])) {
        return "Sure! Please type your **name and WhatsApp number** (with country code) or email right here, and Arun will contact you within 24 hours. 📲";
    }
    if ($has(['hello', 'hi ', 'hey', 'namaste', 'hii', 'helo']) && mb_strlen($m) < 25) {
        return "Hi! 👋 I'm Arun's assistant. I can help with websites, apps, AI agents, Chrome extensions, AEO/GEO and more.\n\nWhat are you planning to build? (Remember — **every price is negotiable**.)";
    }

    // Service match (score by keywords).
    $best = null;
    $bestScore = 0;
    foreach (services_active() as $s) {
        $score = 0;
        foreach (array_filter(array_map('trim', explode(',', mb_strtolower((string)$s['keywords'] . ',' . $s['title'])))) as $kw) {
            $hit = mb_strlen($kw) <= 4 ? (bool)preg_match('/(?<![a-z0-9])' . preg_quote($kw, '/') . '(?![a-z0-9])/u', $m) : str_contains($m, $kw);
            if ($kw !== '' && $hit) {
                $score += mb_strlen($kw);
            }
        }
        if ($score > $bestScore) {
            $best = $s;
            $bestScore = $score;
        }
    }

    $priceAsk = $has(['price', 'cost', 'kitna', 'charge', 'rate', 'budget', 'fees', 'paisa', 'rupee', '₹', 'dollar', 'quote', 'kitne']);

    if ($best) {
        $price = $best['price_usd'] ? $best['price_usd'] . ' (≈ ' . price_text($best) . ')' : price_text($best);
        $out = "**{$best['title']}** — {$best['summary']}\n\n💰 " . $price . " · **negotiable**";
        if ($pk = service_packages($best)) {
            $out .= "\n📦 Packages: " . implode(' · ', array_map(static fn($p) => $p['name'] . ' ' . $p['price'], $pk));
        }
        if ($best['timeline']) {
            $out .= "\n⏱️ Typical timeline: {$best['timeline']}";
        }
        $out .= "\n\n[See full details](/services/{$best['slug']}/) · [Download brochure](/brochure/{$best['slug']}/) · [Enquire now](/services/{$best['slug']}/#enquire)";
        return $out;
    }
    if ($has(['negotiab', 'discount', 'kam', 'less', 'cheap', 'sasta', 'mol', 'bargain'])) {
        return "Absolutely — **every price on this site is negotiable**. 🤝 Nothing is fixed. Share your budget and requirement and we'll find the right scope for it.\n\n[Fill the project brief](/start-project/) or just tell me your budget here.";
    }
    if ($priceAsk) {
        return "Pricing starts from **₹3,500** for a landing page, ₹4,500 for a business website, and custom quotes for apps, AI agents and software. All prices are starting points and **fully negotiable**.\n\nSee [all pricing](/pricing/) or tell me what you need and I'll suggest a budget-friendly scope.";
    }
    if ($has(['time', 'deliver', 'kitne din', 'how long', 'deadline', 'jaldi', 'urgent', 'fast'])) {
        return "Landing pages can be delivered in as little as a day; business websites usually take 3–7 days; apps and custom software are planned in milestones (2–8 weeks). Exact timelines come with your quote.\n\nNeed it urgently? Mention it in the [project brief](/start-project/).";
    }
    if ($has(['payment', 'pay', 'razorpay', 'upi', 'advance', 'invoice'])) {
        return "Payment is milestone-based — usually an advance to start and the rest on delivery. You can pay securely online via **Razorpay** (UPI, cards, netbanking). Some services can be booked directly from their page, and negotiated quotes get a secure payment link.";
    }
    if ($has(['work', 'portfolio', 'project', 'example', 'sample', 'client', 'demo'])) {
        return "Arun has shipped 30+ client websites, a live Play Store app (Tunnl), 6 Amazon Appstore apps, 8 Chrome extensions and many web tools. 🚀\n\nBrowse everything on the [work page](/work/).";
    }
    if ($has(['contact', 'call', 'whatsapp', 'email', 'phone', 'number', 'baat'])) {
        return "You can reach Arun at **" . setting('contact_email', 'itsdevsarun@gmail.com') . "**" . (setting('whatsapp') ? " or WhatsApp **" . setting('whatsapp') . "**" : '') . ". Replies within 24 hours.\n\nOr drop your name + email/WhatsApp right here and he'll get back to you.";
    }
    if ($has(['track', 'status', 'reference', 'ref id'])) {
        return "You can check your project request status any time on the [tracking page](/track/) using your reference ID and email.";
    }
    return "Happy to help! I can tell you about **websites, ecommerce, web apps, Android apps, AI agents, automation, Chrome extensions, SEO and AEO/GEO**.\n\nTell me a bit about your idea — or [start a project brief](/start-project/) and get a negotiable quote within 24 hours.";
}
