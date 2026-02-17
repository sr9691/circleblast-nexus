<?php
defined('ABSPATH') || exit;
return [
	'subject' => 'How did your 1:1 go? Share your notes',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{first_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
Your recent 1:1 has been marked as complete. Take a moment to capture what you discussed — your wins, insights, and action items help the whole group grow.</p>
<table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0;">
<tr><td style="background-color:#2563eb;border-radius:6px;">
<a href="{{meetings_url}}" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:16px;font-weight:600;">Submit Your Notes</a>
</td></tr></table>
<p style="font-size:15px;color:#333;">— The CircleBlast Team</p>',
];
