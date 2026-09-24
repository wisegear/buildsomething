@extends('layouts.site')
@section('title', 'blogshed.uk — Blog Service Terms')
@section('description', 'The terms for using your free blogshed.uk WordPress website responsibly.')
@section('content')
<article class="article wrap">
    <h1>blogshed.uk — Blog Service Terms</h1>
    <p class="lead">blogshed.uk provides free WordPress websites to help people write, create and publish online. The service is provided in good faith, and in return we ask that you use it responsibly.</p>
    <div class="prose">
        <p>By creating an account and using a blogshed.uk website, you agree to the following terms.</p>

        <h2>Your blog</h2>
        <p>Your blog is yours to write and manage. You are responsible for everything you publish, upload or make available through it, including text, images, files, links and comments.</p>
        <p>You must have the necessary rights or permission to publish any content you use.</p>

        <h2>What isn't allowed</h2>
        <p>You must not use blogshed.uk to:</p>
        <ul>
            <li>Publish or promote hate speech, harassment, threats or content intended to abuse or intimidate others.</li>
            <li>Publish, promote or facilitate anything illegal under applicable UK law.</li>
            <li>Publish sexual content involving minors, or any other illegal sexual content.</li>
            <li>Promote terrorism, violent extremism or serious violence.</li>
            <li>Conduct fraud, phishing, scams, impersonation or other deceptive activity intended to cause harm.</li>
            <li>Distribute malware, malicious code or use the service to compromise other systems.</li>
            <li>Publish another person's private or confidential information without lawful justification.</li>
            <li>Repeatedly or deliberately infringe copyright, trademarks or other intellectual-property rights.</li>
            <li>Use the service primarily for spam, automated SEO content, link farming or mass-generated low-value sites.</li>
            <li>Use your website to attack, disrupt, probe or interfere with blogshed.uk or any other service.</li>
        </ul>

        <h2>Free means fair use</h2>
        <p>blogshed.uk is a free service intended for personal blogs, writing, learning and small creative projects. It is not intended to provide unlimited storage, file hosting, commercial infrastructure or other resource-intensive services.</p>
        <p>We may restrict or remove a site that places unreasonable demands on the service or is being used for a purpose substantially different from what blogshed.uk provides.</p>

        <h2>Access to the service</h2>
        <p>blogshed.uk is provided free of charge and without a guarantee of continuous availability. Features may change and the service may occasionally be unavailable.</p>
        <p>We may refuse registrations or restrict access where necessary to protect blogshed.uk, its users or its infrastructure.</p>

        <h2>Breaking the rules</h2>
        <p>If you seriously or deliberately break these terms, <strong>your blog and blogshed.uk account may be suspended or permanently removed without prior warning</strong>.</p>
        <p>We are not required to provide a warning before taking action against abuse, illegal content, spam, fraud, security threats or other serious violations.</p>
        <p>Where appropriate, we may also block further registrations or access to the service.</p>

        <h2>Your content and backups</h2>
        <p>You remain responsible for your content and should keep your own copies of anything important. blogshed.uk should not be treated as your only backup.</p>
        <p>If a blog or account is removed for breaching these terms, we cannot guarantee that its content will be recoverable.</p>

        <h2>Changes to these terms</h2>
        <p>These terms may occasionally be updated as blogshed.uk develops. The current version will always be published on this website.</p>

        <h2>Questions or reports</h2>
        <p>If you have a question about these terms, or believe a blogshed.uk site is being used for abuse or illegal activity, please <a href="{{ route('support.index') }}">contact support</a>.</p>
    </div>
</article>
@endsection
