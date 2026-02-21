<?php
defined('ABSPATH') || exit;
return [
	'subject' => 'New {{type_label}}: {{subject}}',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{admin_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
A member has submitted new feedback through the portal.</p>

<table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;margin:0 0 20px;">
<tr>
<td style="background:#f7fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;">
<p style="margin:0 0 8px;font-size:13px;color:#718096;"><strong>Type:</strong> {{type_label}}</p>
<p style="margin:0 0 8px;font-size:13px;color:#718096;"><strong>From:</strong> {{submitter_name}}</p>
<p style="margin:0 0 8px;font-size:13px;color:#718096;"><strong>Subject:</strong> {{subject}}</p>
<div style="margin:12px 0 0;padding:12px;background:#fff;border-radius:6px;border:1px solid #e2e8f0;">
<p style="margin:0;font-size:14px;color:#333;line-height:1.6;">{{message}}</p>
</div>
</td>
</tr>
</table>

<table role="presentation" cellspacing="0" cellpadding="0"><tr>
<td style="background-color:{{color_primary}};border-radius:6px;">
<a href="{{feedback_url}}" style="display:inline-block;padding:12px 24px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;">View in Portal</a>
</td>
</tr></table>

<p style="font-size:15px;color:#333;margin:16px 0 0;">— The CircleBlast System</p>',
];
