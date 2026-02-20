<?php
/**
 * Email Template: Candidate Accepted (Referrer Notification)
 *
 * Sent to the referring member when their candidate is accepted
 * and their member account has been created.
 */

defined('ABSPATH') || exit;

return [
	'subject' => '🎉 {{candidate_name}} Has Joined CircleBlast!',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{referrer_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
Great news — <strong>{{candidate_name}}</strong> has been accepted into CircleBlast! Their member account has been created and they\'ve received a welcome email with instructions to get started.</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
As their referrer, you played a key role in growing our community. Consider reaching out to welcome them and offer to be their first 1:1 meeting — it\'s a great way to help them feel connected from day one.</p>
<table role="presentation" cellspacing="0" cellpadding="0" style="margin:20px 0;">
<tr><td style="background-color:{{color_primary}};border-radius:6px;">
<a href="{{portal_url}}" style="display:inline-block;padding:12px 24px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;">Open Member Portal</a>
</td></tr></table>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 8px;">Thank you for helping CircleBlast grow stronger.</p>
<p style="font-size:15px;color:#333;margin:0;">— The CircleBlast Team</p>',
];
