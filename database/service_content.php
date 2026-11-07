<?php
declare(strict_types=1);

/*
 * Detailed service content (v2): explainer, business benefits, buying packages, tech stack.
 * Applied automatically by app/upgrade.php to services that don't have it yet.
 * Everything is editable later in Admin → Services. All prices are starting points & negotiable.
 *
 * packages: [name, price text, short note, [features…]]   (2nd package is highlighted as "Most chosen")
 */

return [
    'landing-page' => [
        'overview' => "A landing page is a single, focused web page built for one goal — getting a visitor to take one action: call you, message on WhatsApp, fill an enquiry form, register for an event or buy a product.\n\nUnlike a full website, there are no menus or distractions. Every section — headline, benefits, proof, pricing, FAQ and call-to-action — is arranged in the order a buyer thinks, so the page quietly answers objections and leads to a decision.\n\nIt is the fastest and most budget-friendly way to go online, test a new offer, or turn paid ads (Google, Meta, Instagram) into real enquiries instead of wasted clicks.",
        'benefits' => [
            ['More enquiries from the same traffic', 'One clear goal and one clear button convert far better than a busy home page.'],
            ['Live in days, not weeks', 'Perfect when you need to launch a course, product or campaign quickly.'],
            ['Lower cost per lead on ads', 'Fast, relevant pages improve ad quality scores and reduce what you pay per click.'],
            ['Easy to test and improve', 'Change a headline or offer and measure the result — no full redesign needed.'],
            ['Works perfectly on mobile', 'Most of your visitors are on phones; the page is designed thumb-first.'],
            ['Leads go straight to you', 'Form submissions arrive by email and WhatsApp so you can respond within minutes.'],
        ],
        'packages' => [
            ['Starter', '₹3,500', 'Get online fast', ['1 page, up to 6 sections', 'Mobile-first responsive design', 'Enquiry form + WhatsApp button', 'Basic SEO (title, meta, OG)', 'Delivery in 1–3 days']],
            ['Growth', '₹6,500', 'Built to convert', ['Up to 10 sections incl. FAQ & testimonials', 'Conversion copy guidance', 'Google Analytics / Meta Pixel setup', 'Speed optimisation', 'Thank-you page + 2 revision rounds']],
            ['Campaign Pro', '₹9,999', 'For paid ad campaigns', ['Everything in Growth', 'A/B headline variant', 'Leads saved to Google Sheet / CRM', 'Lead notification on email + WhatsApp', 'Priority support for 30 days']],
        ],
        'tech_stack' => ['HTML5', 'Tailwind CSS', 'JavaScript', 'PHP forms', 'Google Analytics', 'Meta Pixel'],
    ],

    'business-website' => [
        'overview' => "A business website is your company's permanent home on the internet — the place customers check before they call you, visit you or pay you. It explains who you are, what you offer, where you serve and why you are trustworthy.\n\nA professional site has multiple pages (Home, About, Services, Gallery, Testimonials, Blog, Contact), an admin panel so you can update content yourself, and enquiry forms that land directly in your inbox and dashboard.\n\nToday most customers search on Google — and increasingly ask AI tools like ChatGPT — before choosing a provider. A clear, fast, SEO-ready website is what makes you visible, credible and easy to contact, 24 hours a day.",
        'benefits' => [
            ['Credibility that wins trust', 'A modern, professional site instantly makes your business look established and reliable.'],
            ['Found on Google and AI search', 'SEO-ready structure and schema help customers discover you when they search.'],
            ['Enquiries 24×7', 'Your site works while you sleep — collecting enquiries, calls and WhatsApp messages.'],
            ['Update it yourself', 'The admin panel lets you change text, photos, blog posts and see enquiries — no developer needed.'],
            ['Own your audience', 'Unlike social media, your website and its leads belong to you.'],
            ['Measure what works', 'Analytics shows which pages and sources bring you real customers.'],
        ],
        'packages' => [
            ['Starter', '₹4,500', 'Small business essentials', ['5 pages, fully responsive', 'Contact form + WhatsApp', 'Google Maps & social links', 'Basic SEO setup', 'Delivery in 3–5 days']],
            ['Business', '₹9,999', 'Complete online presence', ['7–10 pages incl. blog / gallery', 'Admin panel to manage content & enquiries', 'On-page SEO + sitemap + Search Console', 'Google Analytics', 'Training walkthrough']],
            ['Corporate', '₹19,999+', 'For growing companies', ['15+ pages, custom sections', 'Multi-language option', 'Advanced admin (roles, careers, downloads)', 'Integrations (CRM, payments, booking)', '3 months priority support']],
        ],
        'tech_stack' => ['HTML5', 'Tailwind CSS', 'JavaScript', 'PHP', 'MySQL', 'Schema.org SEO'],
    ],

    'ecommerce-store' => [
        'overview' => "An ecommerce store is a website where customers can browse your products, add them to a cart and pay online — UPI, cards, netbanking or wallets — without calling you or visiting your shop.\n\nBehind the storefront there is a complete back office: product and stock management, order tracking, coupons, shipping status, customer accounts, invoices and automatic email / WhatsApp updates.\n\nYour own store means no marketplace commission on every sale, full control over your brand and pricing, and a customer list you own — so you can sell again through offers and newsletters.",
        'benefits' => [
            ['Sell 24×7, everywhere', 'Accept orders from any city or country, even while your shop is closed.'],
            ['No marketplace commission', 'Keep your margin instead of paying 15–30% per sale to marketplaces.'],
            ['Instant, secure payments', 'Razorpay / PayPal checkout with UPI, cards and netbanking.'],
            ['Less manual work', 'Automatic order confirmations, stock updates and invoices.'],
            ['Repeat customers', 'Customer accounts, coupons and email offers bring buyers back.'],
            ['Clear business insights', 'See best sellers, revenue and order trends in one dashboard.'],
        ],
        'packages' => [
            ['Launch', '₹20,000', 'Start selling online', ['Up to 50 products', 'Razorpay / UPI checkout', 'Order management dashboard', 'Order emails to customer & you', 'Mobile-first storefront']],
            ['Growth', '₹35,000', 'Most complete store', ['Up to 500 products + variants', 'Coupons & discounts', 'Customer accounts & order history', 'Shipping integration (on request)', 'WhatsApp order updates']],
            ['Scale', '₹60,000+', 'Large / B2B catalogues', ['Unlimited products', 'Wholesale / B2B pricing or multi-vendor', 'Inventory / ERP sync', 'Advanced reports & exports', 'Custom features & SLA support']],
        ],
        'tech_stack' => ['PHP', 'MySQL', 'Tailwind CSS', 'JavaScript', 'Razorpay', 'PayPal', 'Shiprocket (optional)'],
    ],

    'web-app' => [
        'overview' => "Web app development means building custom software that runs in the browser — on any laptop, tablet or phone — without installing anything. Think of a learning management system (LMS), a booking system, a CRM, a school ERP, a SaaS product or an internal dashboard for your team.\n\nUnlike a normal website that mainly shows information, a web app lets people log in and do work: manage students, take bookings, track orders, generate reports, accept payments or automate approvals. It is built around your exact process instead of forcing you into someone else's software.\n\nI design the screens and data first, then build in milestones with weekly previews, so you see real progress and can change direction early. You own the code, there are no per-user licence fees, and the system can grow as your business grows.",
        'benefits' => [
            ['Built around your workflow', 'Software that fits how your business actually works — not the other way round.'],
            ['Save hours every week', 'Automate manual registers, spreadsheets, follow-ups and reports.'],
            ['No per-user subscription', 'You own the software; add staff and students without paying monthly licence fees.'],
            ['Access from anywhere', 'Your team and customers use it from any device with a browser.'],
            ['Secure, role-based access', 'Admins, staff and customers each see only what they should.'],
            ['Scales with you', 'Start with an MVP and add modules — payments, apps, integrations — later.'],
        ],
        'packages' => [
            ['MVP', '₹18,000', 'Prove the idea', ['1 core module (e.g. booking or LMS basics)', 'Up to 2 user roles + admin', 'Secure login & dashboard', 'Responsive UI', 'Deployment on your hosting']],
            ['Business', '₹35,000', 'Run your operations', ['3–5 modules', 'Payments (Razorpay) & email notifications', 'Reports, exports & search', 'REST API for future mobile app', 'Milestone demos + 2 months support']],
            ['Enterprise', '₹60,000+', 'Complex platforms & SaaS', ['Custom architecture & multi-tenant options', 'Third-party integrations (CRM, ERP, WhatsApp)', 'Audit logs & advanced permissions', 'Performance & security hardening', 'Documentation, handover & SLA']],
        ],
        'tech_stack' => ['PHP', 'MySQL', 'JavaScript', 'Tailwind CSS', 'REST APIs', 'Razorpay', 'Python (on request)'],
    ],

    'android-app' => [
        'overview' => "Android app development means building an app your customers download from the Google Play Store or Amazon Appstore and keep on their phone — always one tap away.\n\nI build apps with Flutter, which gives a smooth, native-feel experience and a single codebase that can also be released on iOS later. The app can work offline, send push notifications, accept in-app purchases or subscriptions, and connect to a backend (Firebase or PHP/MySQL) with an admin panel to manage content.\n\nI have published Tunnl on Google Play and six apps on the Amazon Appstore, so store policies, listings, privacy forms and reviews are handled end-to-end — you get a live app, not just source code.",
        'benefits' => [
            ['Always on the customer\'s phone', 'Your brand lives on their home screen, not lost in a browser tab.'],
            ['Push notifications', 'Reach users instantly with offers, reminders and updates — no ad spend.'],
            ['New revenue streams', 'Paid unlocks, subscriptions, in-app purchases or ads.'],
            ['Works offline', 'Great for education, kids and field apps where internet is unreliable.'],
            ['Better engagement', 'Apps keep users coming back more often than websites.'],
            ['Store publishing done for you', 'Listing, screenshots, policies and review handled end-to-end.'],
        ],
        'packages' => [
            ['Starter', '₹20,000', 'Simple, polished app', ['Up to 8 screens', 'Flutter (Android, iOS-ready code)', 'Firebase backend', 'Google Play publishing', '1 month support']],
            ['Pro', '₹35,000', 'Most chosen for businesses', ['Up to 20 screens', 'Custom backend + admin panel', 'Payments / in-app purchases', 'Push notifications & analytics', 'Play Store + Amazon Appstore']],
            ['Premium', '₹50,000+', 'Feature-rich products', ['Complex features (live data, video, chat)', 'Multi-language', 'Leaderboards / gamification', 'iOS build on request', '3 months priority support']],
        ],
        'tech_stack' => ['Flutter', 'Dart', 'Firebase', 'PHP APIs', 'MySQL', 'Google Play', 'Amazon Appstore'],
    ],

    'ai-agent' => [
        'overview' => "An AI agent (or AI chatbot) is a smart assistant trained on your business — your services, prices, FAQs, policies and documents — that talks to customers on your website or WhatsApp in natural language, 24×7.\n\nIt doesn't just reply with fixed answers. It understands questions in English, Hindi or Hinglish, recommends the right product or service, qualifies the lead (budget, timeline, requirement), collects name and phone number, and hands hot leads to you instantly. Every conversation is saved in a dashboard.\n\nThe assistant on this very website is an example. It is built on models like GPT, Gemini or Llama (via Groq) with guard-rails, so it stays on-topic, never invents prices and always escalates to a human when needed.",
        'benefits' => [
            ['Instant replies, day and night', 'Customers get answers in seconds — even at 2 AM — instead of waiting for you.'],
            ['More leads, captured automatically', 'The agent asks the right questions and saves contact details to your CRM.'],
            ['Less time on repeat questions', 'Pricing, timing, location and process questions are handled for you.'],
            ['Qualified leads only', 'You talk to people who already shared budget and requirement.'],
            ['Speaks your customer\'s language', 'English, Hindi and Hinglish conversations feel natural.'],
            ['Full visibility', 'Read every transcript and see what customers really ask.'],
        ],
        'packages' => [
            ['Website Bot', '₹8,000', 'Answer & capture', ['Trained on your services, prices & FAQs', 'Branded chat widget for your website', 'Lead capture (name, phone, email)', 'Email alerts for new leads', 'Conversation dashboard']],
            ['Lead Agent', '₹15,000', 'Website + WhatsApp', ['Everything in Website Bot', 'WhatsApp integration', 'Lead qualification flow', 'Google Sheet / CRM sync', 'Hand-off to human']],
            ['Custom Agent', '₹30,000+', 'Advanced AI workflows', ['Answers from your PDFs & documents (RAG)', 'Actions: booking, order status, quotes', 'Multi-channel (web, WhatsApp, Telegram)', 'Analytics & tuning', 'Ongoing improvements']],
        ],
        'tech_stack' => ['OpenAI GPT', 'Google Gemini', 'Groq / Llama', 'PHP', 'WhatsApp API', 'Google Sheets'],
    ],

    'automation' => [
        'overview' => "Business automation connects the tools you already use so repetitive work happens on its own — without someone copying data, sending reminders or chasing follow-ups manually.\n\nExamples: a new enquiry instantly creates a CRM entry and sends a WhatsApp reply; an appointment triggers an SMS reminder the day before; a payment generates an invoice and thank-you email; a daily report lands in your inbox every morning.\n\nI map your current process, find the tasks that waste the most time, and build reliable automations with logging and failure alerts so nothing slips through the cracks.",
        'benefits' => [
            ['Save hours every week', 'Free your team from copy-paste, reminders and follow-ups.'],
            ['Faster response to customers', 'Instant auto-replies and alerts mean no lead waits.'],
            ['Fewer human errors', 'Automations do the same job correctly every time.'],
            ['Fewer no-shows', 'Automatic reminders for appointments, classes and payments.'],
            ['Works with your tools', 'WhatsApp, SMS, Gmail, Google Sheets, CRMs and payment gateways.'],
            ['Peace of mind', 'Logs and alerts tell you if anything ever fails.'],
        ],
        'packages' => [
            ['Quick Win', 'From ₹5,000', 'One workflow', ['1 automation (e.g. form → WhatsApp + Sheet)', 'Up to 2 tools connected', 'Testing & go-live', 'Simple documentation']],
            ['Business', 'From ₹12,000', 'Automate a department', ['3–5 automations', 'WhatsApp / SMS (Twilio) / email', 'Scheduled jobs & reports', 'Error alerts & logs', '1 month support']],
            ['Operations Suite', 'Custom quote', 'End-to-end automation', ['Process audit & roadmap', 'Custom integrations & webhooks', 'Dashboards & monitoring', 'Team training', 'Ongoing maintenance option']],
        ],
        'tech_stack' => ['PHP', 'Python', 'Cron jobs', 'Twilio', 'WhatsApp API', 'Gmail / SMTP', 'Google Sheets', 'Webhooks'],
    ],

    'chrome-extension' => [
        'overview' => "A Chrome extension is a small piece of software that adds new features directly inside the Google Chrome browser (and Edge, Brave and other Chromium browsers). It can work on any website: read the page, add buttons, open a side panel, automate clicks, fill forms or call AI.\n\nBusinesses use extensions as productivity tools for their team, as companion products for their SaaS, or as standalone products sold with free and Pro plans.\n\nI have shipped 8 extensions on the Chrome Web Store, including AI tools like InboxSwift (email replies), Aptis (learning), Kognit (second brain) and QuickGlance (explain anything) — built on Manifest V3 with side panels, licensing and Pro upgrades.",
        'benefits' => [
            ['Work where your users already are', 'Features appear right inside Gmail, LinkedIn or any website.'],
            ['Boost team productivity', 'Automate repetitive browser tasks your team does daily.'],
            ['A product you can sell', 'Free + Pro plans with licence keys create recurring revenue.'],
            ['Distribution via Chrome Web Store', 'Millions of users can find and install it in one click.'],
            ['AI superpowers', 'Summarise, write, translate or explain any page with GPT / Gemini / Groq.'],
            ['Lightweight & private', 'Fast, local-first design with clear permission usage.'],
        ],
        'packages' => [
            ['Basic', '₹5,000', 'Simple utility', ['Popup UI + 1–2 features', 'Manifest V3', 'Settings & local storage', 'Web Store listing support']],
            ['Pro', '₹10,000', 'Most chosen', ['Side panel + content scripts', 'AI integration (bring-your-own-key or server)', 'Light / dark themes', 'Context menus & shortcuts', 'Web Store publishing']],
            ['Product', '₹15,000+', 'Sellable SaaS extension', ['Free + Pro licensing', 'License server / backend', 'Usage limits & analytics', 'Landing page for the extension', 'Updates & review handling']],
        ],
        'tech_stack' => ['JavaScript', 'Chrome Manifest V3', 'Side Panel API', 'Groq / Gemini / GPT', 'PHP license server'],
    ],

    'aeo-geo-starter' => [
        'overview' => "AEO (Answer Engine Optimization) and GEO (Generative Engine Optimization) help your business get found and chosen in AI search — ChatGPT, Gemini, Perplexity, Google AI Overviews and AI Mode.\n\nMore and more customers no longer scroll through ten blue links. They ask an AI: “Who offers this service in my city?” or “Which tool fits our team?”. The AI reads trusted web pages and writes a short answer — often citing or linking the sources it used. If your page is clear, specific and easy to understand, it may be cited, and your business may enter the customer's shortlist.\n\nAEO improves your content so it works as a clear answer to real customer questions. GEO helps AI search understand your services, expertise, service area and proof. Together they build on solid SEO foundations — there is no secret “AI switch”, and nobody can honestly guarantee AI mentions. What you get is focused, reviewable improvements and honest measurement.",
        'benefits' => [
            ['Visibility in AI answers', 'Clear, citable pages give AI tools a reason to mention and link your business.'],
            ['Better Google results too', 'AEO/GEO builds on SEO fundamentals, so classic search benefits as well.'],
            ['Higher-intent visitors', 'People arriving from AI answers already know what they want.'],
            ['Answers before objections', 'Your pages answer the questions buyers ask before choosing a provider.'],
            ['Clear next step', 'Every optimised page ends with an obvious call, quote or enquiry option.'],
            ['Honest, measurable', 'Baseline first, then track Search Console, AI referrals and enquiries.'],
        ],
        'packages' => [
            ['Starter', '$199 one-time', '≈ ₹16,999 · one website, focused start', ['Basic site audit', '10 customer questions', '2 existing pages optimised', 'Relevant structured data', 'Summary + action plan, 1 revision']],
            ['Growth', 'Custom quote', 'More pages, more questions', ['Everything in Starter', 'More priority pages optimised', 'Expanded customer question set', 'Service-area & entity clarity', 'Internal linking plan']],
            ['Ongoing', 'Custom monthly', 'Continuous improvement', ['Monthly content & page updates', 'Search Console & AI referral reporting', 'New questions as your market changes', 'Quarterly review call']],
        ],
        'tech_stack' => ['Google Search Console', 'Schema.org', 'Analytics', 'Crawler access (robots.txt)', 'Content strategy'],
    ],

    'seo' => [
        'overview' => "SEO (Search Engine Optimization) is the work that helps your website appear on Google when people search for what you offer — “home tutor in Patna”, “best CBSE school near me”, “website developer for coaching institute”.\n\nIt has three parts: technical SEO (Google can crawl, index and load your site fast), on-page SEO (each page clearly targets the right searches with good titles, headings and content), and local SEO (your Google Business Profile, reviews and location signals).\n\nUnlike ads, SEO traffic doesn't stop when you stop paying. It compounds over time and brings customers who are already searching for you.",
        'benefits' => [
            ['Free, compounding traffic', 'Rankings keep bringing visitors without paying per click.'],
            ['Customers already looking for you', 'Search traffic has strong buying intent.'],
            ['Win local searches', '“Near me” searches and Google Maps visibility for your area.'],
            ['Faster website', 'Technical fixes improve speed and user experience.'],
            ['Trust & authority', 'Appearing on page one makes your brand look like the market leader.'],
            ['Clear reporting', 'Know exactly which keywords and pages bring enquiries.'],
        ],
        'packages' => [
            ['SEO Audit', 'From ₹4,999', 'Know what to fix', ['Technical crawl & index check', 'Speed & mobile review', 'Keyword opportunities', 'Prioritised fix list']],
            ['On-page + Local', 'From ₹9,999', 'Most chosen', ['Audit + fixes on key pages', 'Titles, meta, headings & schema', 'Google Business Profile optimisation', 'Search Console & Analytics setup', 'Before/after report']],
            ['Monthly SEO', 'Custom monthly', 'Ongoing growth', ['Monthly content plan', 'Continuous on-page improvements', 'Local citations & reviews strategy', 'Monthly ranking & traffic report']],
        ],
        'tech_stack' => ['Google Search Console', 'Google Analytics', 'Schema.org', 'PageSpeed Insights', 'Google Business Profile'],
    ],

    'digital-marketing' => [
        'overview' => "Digital marketing is how you reach new customers online — through social media (Instagram, Facebook, LinkedIn, YouTube), content (posts, reels, blogs), email newsletters and campaigns that send people to your website or WhatsApp.\n\nGood marketing is not just posting every day. It starts with who your customer is, what offer will make them act, and which channel they use. Then every campaign is tracked with UTM links so you see exactly which effort brought enquiries and sales.\n\nBecause I also build the website and landing pages, the whole journey — post → page → enquiry → follow-up — is connected and measurable.",
        'benefits' => [
            ['Reach the right audience', 'Target by city, age, interest and intent.'],
            ['Consistent brand presence', 'A planned content calendar keeps you visible every week.'],
            ['More enquiries, measurably', 'Every campaign is tracked down to the lead.'],
            ['Nurture with email', 'Automated sequences turn interested people into customers.'],
            ['Landing pages that convert', 'Campaign traffic goes to pages built for that offer.'],
            ['Monthly clarity', 'Know what worked, what didn\'t and what is next.'],
        ],
        'packages' => [
            ['Starter', 'From ₹7,999/month', 'Stay consistent', ['Content calendar', '12 social posts / month', 'Captions & hashtags', 'Monthly report']],
            ['Growth', 'From ₹14,999/month', 'Most chosen', ['20 posts + 4 reel scripts', 'Email newsletter', 'Campaign landing page', 'UTM tracking & lead reporting', 'Monthly strategy call']],
            ['Campaign', 'Custom quote', 'Launches & ads', ['Launch strategy', 'Ad creatives & copy', 'Ad campaign setup (spend extra)', 'Funnel + follow-up automation']],
        ],
        'tech_stack' => ['Meta Business Suite', 'Google Ads', 'Email marketing', 'Canva / Figma', 'UTM analytics'],
    ],

    'ui-ux-design' => [
        'overview' => "UI/UX design decides how your website or app looks (UI — user interface) and how easy it is to use (UX — user experience). Good design is not decoration; it is what makes people understand your offer quickly, trust you and complete the action you want.\n\nThe process starts with user flows and wireframes (structure), then moves to polished screens with a consistent design system — colours, typography, buttons, cards and states — and finally a clickable prototype you can test before a single line of code is written.\n\nDesigning first saves money: changes on a screen take minutes, changes after development take days.",
        'benefits' => [
            ['First impressions that convert', 'Clean, modern design builds instant trust.'],
            ['Easier to use', 'Clear flows reduce confusion, drop-offs and support calls.'],
            ['Save development cost', 'Fix ideas on screens before they become expensive code.'],
            ['Consistent brand', 'A design system keeps every page and screen on-brand.'],
            ['Faster development', 'Developers build quicker from clear, complete designs.'],
            ['Test before you build', 'Clickable prototypes let you get real feedback early.'],
        ],
        'packages' => [
            ['Website UI', 'From ₹5,000', 'Up to 5 screens', ['Wireframes', 'High-fidelity desktop + mobile', '1 revision round', 'Handoff files']],
            ['App / Dashboard UI', 'From ₹12,000', 'Most chosen', ['User flows & wireframes', 'Up to 20 screens', 'Component library', 'Clickable prototype', '2 revision rounds']],
            ['Design System', 'Custom quote', 'For products & teams', ['Full component system', 'Accessibility review', 'Documentation', 'Developer handoff support']],
        ],
        'tech_stack' => ['Figma', 'Design systems', 'Prototyping', 'Accessibility (WCAG)', 'Tailwind-ready specs'],
    ],

    'website-redesign' => [
        'overview' => "A website redesign upgrades an old, slow or outdated website into a fast, modern, mobile-first site — without losing the Google rankings and content you have already built.\n\nSigns you need one: the site looks dated, loads slowly on phones, is hard to update, doesn't bring enquiries, or no longer matches your brand. A redesign fixes the design, speed, structure and enquiry flow, and moves everything to a clean admin panel.\n\nThe key is a safe migration: existing URLs are mapped with 301 redirects, important content is kept and improved, and the switch-over happens with zero downtime.",
        'benefits' => [
            ['Modern look, instant trust', 'Your brand looks current and premium again.'],
            ['Keep your SEO', 'Careful URL mapping and redirects protect existing rankings.'],
            ['Much faster', 'Better Core Web Vitals improve both rankings and conversions.'],
            ['More enquiries', 'Clear CTAs and better forms turn visitors into leads.'],
            ['Easy to manage', 'A simple admin panel replaces painful old CMS setups.'],
            ['Mobile-first', 'Designed for the phones most of your visitors use.'],
        ],
        'packages' => [
            ['Refresh', 'From ₹5,000', 'New look, same structure', ['Modern redesign up to 5 pages', 'Mobile responsive', 'Speed basics', 'Content carried over']],
            ['Rebuild', 'From ₹12,000', 'Most chosen', ['New design + new code', 'Admin panel', 'SEO-safe migration & redirects', 'Speed optimisation', 'Improved enquiry flow']],
            ['Full Migration', 'Custom quote', 'Large / complex sites', ['Content audit & restructure', 'Data migration (blog, products)', 'Advanced integrations', 'Post-launch monitoring']],
        ],
        'tech_stack' => ['HTML5', 'Tailwind CSS', 'PHP', 'MySQL', '301 redirects', 'Core Web Vitals'],
    ],

    'maintenance-support' => [
        'overview' => "Website maintenance keeps your site secure, fast, backed-up and up-to-date after launch — so you never wake up to a hacked, broken or expired website.\n\nA maintenance plan covers regular backups, security checks, updates, uptime monitoring, small content changes and quick bug fixes, with a direct line to the developer on WhatsApp.\n\nIt costs far less than fixing a disaster, and lets you focus on running your business while your website keeps working for you.",
        'benefits' => [
            ['Always backed up', 'Restore quickly if anything ever goes wrong.'],
            ['Secure and updated', 'Regular checks keep hackers and malware away.'],
            ['Fast fixes', 'Bugs and small issues solved without hunting for a developer.'],
            ['Content kept fresh', 'Send text and photo updates — they get done.'],
            ['Uptime monitoring', 'Know immediately if your site goes down.'],
            ['Predictable cost', 'One small monthly fee instead of surprise bills.'],
        ],
        'packages' => [
            ['Basic', 'From ₹999/month', 'Peace of mind', ['Monthly backups', 'Security & uptime checks', '30 min of small edits', 'Email support']],
            ['Business', 'From ₹2,499/month', 'Most chosen', ['Weekly backups', 'Updates & malware checks', '2 hours of edits / fixes', 'WhatsApp support', 'Monthly report']],
            ['Priority', 'From ₹4,999/month', 'For stores & web apps', ['Daily backups', 'Priority response', '5 hours of development', 'Performance tuning', 'Quarterly improvement call']],
        ],
        'tech_stack' => ['Backups', 'Uptime monitoring', 'SSL', 'Security scans', 'PHP / MySQL updates'],
    ],

    'custom-idea' => [
        'overview' => "Have something that doesn't fit a standard box? Games, tools, data dashboards, scrapers, browser automation, integrations between two systems, an MVP for a startup idea — if it can be built with code, we can talk about it.\n\nWe start with a feasibility check: is it possible, what is the smartest way to build it, and what is the smallest version that proves the idea? You then get an honest, current market-rate range with milestones — no inflated fixed pricing.\n\nThis approach protects your budget: you validate the idea first and invest more only when it works.",
        'benefits' => [
            ['Honest feasibility advice', 'Know if and how your idea can be built — before spending.'],
            ['Smallest useful version first', 'Launch an MVP quickly and learn from real users.'],
            ['Fair, market-rate pricing', 'A transparent range instead of agency mark-ups.'],
            ['Milestone delivery', 'Pay and review in stages; change direction early.'],
            ['Full-stack under one roof', 'Web, mobile, extensions, AI and automation together.'],
            ['You own the result', 'Code and IP are yours after payment.'],
        ],
        'packages' => [
            ['Feasibility', 'Free', '30-minute discovery', ['Idea discussion', 'Technical approach', 'Rough cost & timeline range']],
            ['MVP', 'Custom quote', 'Most chosen', ['Scoped smallest version', 'Clickable / working prototype', 'Milestone demos', 'Launch support']],
            ['Full Build', 'Custom quote', 'Production product', ['Complete feature set', 'Scalable architecture', 'Documentation & handover', 'Ongoing support option']],
        ],
        'tech_stack' => ['PHP', 'Python', 'JavaScript', 'Flutter', 'AI APIs', 'MySQL'],
    ],

    'free-consultation' => [
        'overview' => "Not sure whether you need a landing page, a full website, an app or AEO/GEO? Book a free 20–30 minute requirement call on phone, WhatsApp or Google Meet.\n\nWe talk about your business, goal, budget and timeline. You get an honest recommendation — even if that means starting smaller, or that you don't need me at all — followed by a clear, written, negotiable quote within 24 hours.\n\nNo pressure, no spam and no obligation. Decide in your own time.",
        'benefits' => [
            ['Clarity before spending', 'Know exactly what you need and why.'],
            ['Right-size scope', 'Must-haves first, nice-to-haves later — fits your budget.'],
            ['Written quote in 24h', 'Clear deliverables, timeline and negotiable price.'],
            ['Talk to the developer', 'No sales middleman — straight technical answers.'],
            ['Budget-friendly options', 'Smart starting points for tight budgets.'],
            ['Zero obligation', 'Use the advice even if you don\'t hire me.'],
        ],
        'packages' => [
            ['Free Call', 'Free', '20–30 minutes', ['Phone / WhatsApp / Google Meet', 'Requirement discussion', 'Honest recommendation', 'Written quote within 24h']],
        ],
        'tech_stack' => [],
    ],
];
