<?php
/**
 * Email Template: Recruitment Invitation
 *
 * Sent to a candidate when they are moved to the "Invited" stage.
 */

defined('ABSPATH') || exit;

return [
	'subject' => 'You\'re Invited to Visit CircleBlast, {{candidate_first_name}}!',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{candidate_first_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
{{referrer_name}} from CircleBlast has recommended you as someone who would be a great fit for our professional networking group.</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
<strong>CircleBlast</strong> is a curated community of professionals who meet monthly to build meaningful relationships, exchange referrals, and support each other\'s growth. Our members come from diverse industries and share a commitment to collaboration over competition.</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 8px;"><strong>We\'d love for you to visit one of our upcoming meetings to see if it\'s a fit.</strong> Here\'s what to expect:</p>
<ul style="font-size:15px;color:#333;line-height:1.8;margin:0 0 20px;padding-left:20px;">
<li>A welcoming group of professionals genuinely interested in helping each other</li>
<li>Structured but relaxed format focused on relationship-building</li>
<li>No pressure — come see what we\'re about</li>
</ul>
{{invitation_notes_block}}
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
If you have any questions beforehand, feel free to reach out to {{referrer_name}} or reply to this email.</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 8px;">We look forward to meeting you!</p>
<p style="font-size:15px;color:#333;margin:0;">— The CircleBlast Team</p>',
];
