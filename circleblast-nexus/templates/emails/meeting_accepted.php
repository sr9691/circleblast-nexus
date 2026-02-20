<?php
defined('ABSPATH') || exit;
return [
	'subject' => '{{responder_name}} accepted your 1:1 request!',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{first_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
Great news — <strong>{{responder_name}}</strong> has accepted your 1:1 meeting request! Reach out to coordinate a time that works for both of you.</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">After you meet, click below to submit your notes:</p>
<table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0;">
<tr><td style="background-color:{{color_primary}};border-radius:6px;">
<a href="{{complete_url}}" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:16px;font-weight:600;">We Met — Submit Notes</a>
</td></tr></table>
<p style="font-size:13px;color:#a0aec0;">No login required. Click this after your meeting to record your notes.</p>
<p style="font-size:15px;color:#333;">— The CircleBlast Team</p>',
];
