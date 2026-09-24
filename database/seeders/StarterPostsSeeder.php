<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

class StarterPostsSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::where('email', 'lee@wisener.net')->firstOrFail();
        $posts = [
            [
                'title' => 'Your idea doesn’t need to be finished to begin',
                'slug' => 'your-idea-doesnt-need-to-be-finished',
                'seo_summary' => 'You don’t need a grand plan to make a website. Start with one small thing you’d like to share.',
                'body' => '<p>It’s easy to imagine a website as something you launch only when everything is ready. The design is polished. The words are perfect. The plan stretches months into the future.</p><p>But a website can begin with something much smaller: a single page, a short introduction, or a note about something you’ve learned.</p><h2>Choose one thing</h2><p>Think about a subject you return to without being asked. Your garden. The books on your bedside table. A hobby you’re learning. A question you can’t quite leave alone.</p><p>Write a few sentences about why it matters to you. That’s enough for a first post.</p><h2>Let tools help, without losing your voice</h2><p>If you’re curious about AI, try asking it to suggest questions a beginner might have about your subject. Use those questions as starting points. Check any facts, add your own experience, and keep the words that sound like you.</p><p>You can also leave AI out entirely. A notebook and a little time are excellent tools too.</p><h2>Give yourself permission to change</h2><p>Your first version is a beginning. You can edit a page, change a title, or head in a new direction as you learn. Make one small thing, share it, and see what you want to make next.</p>',
            ],
            [
                'title' => 'What is hosting, in plain English?',
                'slug' => 'what-is-hosting-in-plain-english',
                'seo_summary' => 'A simple explanation of where a website lives, how people find it, and where WordPress fits in.',
                'body' => '<p>Before you make a website, you’ll probably come across a few unfamiliar words. Hosting is one of them. The idea behind it is simpler than the name might suggest.</p><h2>A place for your website to live</h2><p>A website is made up of information: words, images, settings, and the software that puts everything together. Hosting provides a place on an internet-connected computer where that information can live and be served to visitors.</p><p>Think of hosting as the space your website occupies.</p><h2>An address people can visit</h2><p>A domain name is an address people use to find a website. It is different from hosting: the address helps people find the space, while hosting makes the website available there.</p><h2>A way to create and edit</h2><p>WordPress is the software you use to manage the content. You can write posts, create pages, and choose how your website looks through its dashboard.</p><p>Hosting, an address, and WordPress work together. You don’t need to become an expert in all three before writing your first page.</p><h2>Getting started with Build</h2><p>The offer here is a free WordPress site. Create an account, then use the contact link in your account area to arrange setup with Lee. Automatic setup isn’t available yet, so that first step currently includes a conversation.</p>',
            ],
            [
                'title' => 'Your first WordPress site: start with three small things',
                'slug' => 'your-first-wordpress-site',
                'seo_summary' => 'An introduction, a first post, and a little personality. A gentle starting point for your new site.',
                'body' => '<p>An empty website can feel a little like a blank sheet of paper. There are lots of possibilities, and it isn’t always obvious where to start. Try these three small steps once your WordPress site is ready.</p><h2>1. Say hello</h2><p>Create a short About page. Tell visitors who you are, what this space is for, and what you’re curious about. A few honest sentences are more useful than trying to sound like a large organisation.</p><h2>2. Write one useful or interesting post</h2><p>Share something you’ve learned, a small project, or an observation from your day. Give it a clear title and break longer thoughts into short paragraphs.</p><p>Pages usually hold information that stays useful over time, such as your introduction. Posts are a good home for new stories and updates.</p><h2>3. Add a little personality</h2><p>Choose a simple look that makes your words easy to read. If you add a photograph, use one you own or have permission to share, and include a short description for people who can’t see it.</p><h2>Then take a look around</h2><p>Open the site on your phone. Read it as a visitor would. Can you find the introduction? Is the text comfortable to read? Does the first post say what you wanted it to?</p><p>Make a small adjustment if it helps, then share the link with someone. You’ve made a start.</p>',
            ],
        ];
        foreach ($posts as $index => $data) {
            if (! Post::where('slug', $data['slug'])->exists()) {
                $post = new Post($data + ['is_published' => true, 'post_date' => today()->subDays($index)]);
                $post->user_id = $author->id;
                $post->save();
            }
        }
    }
}
