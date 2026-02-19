<?php
/**
 * Email Template: Recruitment - Visit Feedback Received (Referrer Notification)
 *
 * Sent to the referring member when their candidate clicks a response
 * on the visit feedback survey. Includes the response and suggested actions.
 */

defined('ABSPATH') || exit;

return [
	'subject' => '{{candidate_name}} responded to the visit survey',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{referrer_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
Your referral <strong>{{candidate_name}}</strong> just responded to our post-visit survey.</p>

<div style="background:#f8f5fa;border-radius:10px;padding:18px 24px;margin:16px 0;">
<p style="font-size:13px;font-weight:600;color:#6b7280;margin:0 0 6px;text-transform:uppercase;letter-spacing:0.5px;">Their Response</p>
<p style="font-size:18px;font-weight:700;color:#4a154b;margin:0;">{{feedback_label}}</p>
</div>

{{action_block}}

<p style="font-size:15px;color:#333;line-height:1.6;margin:16px 0 8px;">
Your involvement makes a huge difference in the recruitment process. Thank you for helping CircleBlast grow!</p>
<p style="font-size:15px;color:#333;margin:0;">— The CircleBlast Team</p>',
];
