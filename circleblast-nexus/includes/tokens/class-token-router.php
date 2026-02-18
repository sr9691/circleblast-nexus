<?php
/**
 * Token Router
 *
 * Intercepts ?cbnexus_token=... URLs and dispatches to the correct handler.
 * This is the backbone of the "zero login" experience — members click a
 * link in their email and land directly on the action page.
 *
 * Supported actions:
 *   - accept_meeting     → Accept and redirect to confirmation page
 *   - decline_meeting    → Decline and redirect to confirmation page
 *   - complete_meeting   → Mark meeting complete, redirect to notes form
 *   - submit_notes       → Render notes form (multi-use token)
 *   - update_action      → Render action item update form (multi-use token)
 *   - view_circleup      → View CircleUp summary (multi-use token)
 *   - forward_circleup   → Render forward form (multi-use token)
 *   - quick_share        → Render quick share form (multi-use token)
 */

defined('ABSPATH') || exit;

final class CBNexus_Token_Router {

	public static function init(): void {
		add_action('template_redirect', [__CLASS__, 'handle'], 5);
	}

	public static function handle(): void {
		if (!isset($_GET['cbnexus_token'])) {
			return;
		}

		$raw_token = sanitize_text_field(wp_unslash($_GET['cbnexus_token']));

		// For form submissions (POST), validate and consume.
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cbnexus_token_action'])) {
			self::handle_post($raw_token);
			return;
		}

		// For GET requests, peek first to decide if it's a one-shot action or a form render.
		$data = CBNexus_Token_Service::peek($raw_token);
		if (!$data) {
			self::render_page('Link Expired', '<p>This link has expired or has already been used.</p><p><a href="' . esc_url(home_url()) . '">Go to CircleBlast</a></p>');
			exit;
		}

		switch ($data['action']) {
			case 'accept_meeting':
				$consumed = CBNexus_Token_Service::validate($raw_token);
				if ($consumed) {
					$mid = $consumed['payload']['meeting_id'] ?? 0;
					$meeting = CBNexus_Meeting_Repository::get($mid);
					if ($meeting && $meeting->status === 'suggested') {
						CBNexus_Meeting_Repository::update($mid, ['status' => 'pending']);
					}
					CBNexus_Meeting_Service::accept($mid, $consumed['user_id']);
					$other = CBNexus_Meeting_Repository::get_other_member(
						CBNexus_Meeting_Repository::get($mid), $consumed['user_id']
					);
					$other_profile = CBNexus_Member_Repository::get_profile($other);
					$name = $other_profile ? $other_profile['display_name'] : 'your match';
					self::render_page('Meeting Accepted! ✅', '<p>You\'ve accepted the 1:1 with <strong>' . esc_html($name) . '</strong>.</p><p>Coordinate a time that works for both of you — we\'ll send a reminder before your meeting.</p>');
				}
				exit;

			case 'decline_meeting':
				$consumed = CBNexus_Token_Service::validate($raw_token);
				if ($consumed) {
					$mid = $consumed['payload']['meeting_id'] ?? 0;
					$meeting = CBNexus_Meeting_Repository::get($mid);
					if ($meeting && $meeting->status === 'suggested') {
						CBNexus_Meeting_Repository::update($mid, ['status' => 'pending']);
					}
					CBNexus_Meeting_Service::decline($mid, $consumed['user_id']);
					self::render_page('Meeting Declined', '<p>No worries — we\'ll match you with someone else next time.</p>');
				}
				exit;

			case 'complete_meeting':
				$consumed = CBNexus_Token_Service::validate($raw_token);
				if ($consumed) {
					$mid = $consumed['payload']['meeting_id'] ?? 0;
					CBNexus_Meeting_Service::complete($mid, $consumed['user_id']);
					// Generate a notes token and redirect to it.
					$notes_token = CBNexus_Token_Service::generate($consumed['user_id'], 'submit_notes', ['meeting_id' => $mid], 14, true);
					wp_redirect(CBNexus_Token_Service::url($notes_token));
				}
				exit;

			case 'submit_notes':
				self::render_notes_form($data, $raw_token);
				exit;

			case 'update_action':
				self::render_action_update_form($data, $raw_token);
				exit;

			case 'view_circleup':
				self::render_circleup_view($data);
				exit;

			case 'forward_circleup':
				self::render_forward_form($data, $raw_token);
				exit;

			case 'quick_share':
				self::render_quick_share_form($data, $raw_token);
				exit;

			default:
				self::render_page('Unknown Action', '<p>This link is not recognized.</p>');
				exit;
		}
	}

	// ─── POST Handlers ─────────────────────────────────────────────────

	private static function handle_post(string $raw_token): void {
		$action = sanitize_key($_POST['cbnexus_token_action'] ?? '');

		// Re-peek to get user context (multi-use tokens survive).
		$data = CBNexus_Token_Service::peek($raw_token);
		if (!$data) {
			self::render_page('Link Expired', '<p>This link has expired.</p>');
			exit;
		}

		switch ($action) {
			case 'submit_notes':
				self::process_notes_submission($data, $raw_token);
				exit;

			case 'update_action':
				self::process_action_update($data);
				exit;

			case 'forward_circleup':
				self::process_circleup_forward($data);
				exit;

			case 'quick_share':
				self::process_quick_share($data);
				exit;

			default:
				self::render_page('Error', '<p>Unknown form action.</p>');
				exit;
		}
	}

	// ─── Notes Form ────────────────────────────────────────────────────

	private static function render_notes_form(array $data, string $token): void {
		$meeting_id = $data['payload']['meeting_id'] ?? 0;
		$meeting    = CBNexus_Meeting_Repository::get($meeting_id);
		$other_id   = $meeting ? CBNexus_Meeting_Repository::get_other_member($meeting, $data['user_id']) : 0;
		$other      = $other_id ? CBNexus_Member_Repository::get_profile($other_id) : null;
		$other_name = $other ? $other['display_name'] : 'your partner';

		$form = '
		<p>How was your 1:1 with <strong>' . esc_html($other_name) . '</strong>?</p>
		<form method="post" style="max-width:500px;">
			<input type="hidden" name="cbnexus_token" value="' . esc_attr($token) . '" />
			<input type="hidden" name="cbnexus_token_action" value="submit_notes" />

			<label style="display:block;margin:16px 0 4px;font-weight:600;font-size:14px;">🏆 Wins</label>
			<textarea name="wins" rows="3" style="width:100%;padding:10px;border:1px solid #e0d6e8;border-radius:10px;font-family:DM Sans,sans-serif;" placeholder="What went well? Any results or outcomes?"></textarea>

			<label style="display:block;margin:16px 0 4px;font-weight:600;font-size:14px;">💡 Insights</label>
			<textarea name="insights" rows="3" style="width:100%;padding:10px;border:1px solid #e0d6e8;border-radius:10px;font-family:DM Sans,sans-serif;" placeholder="What did you learn?"></textarea>

			<label style="display:block;margin:16px 0 4px;font-weight:600;font-size:14px;">✅ Action Items</label>
			<textarea name="action_items" rows="3" style="width:100%;padding:10px;border:1px solid #e0d6e8;border-radius:10px;font-family:DM Sans,sans-serif;" placeholder="What are the next steps?"></textarea>

			<label style="display:block;margin:16px 0 4px;font-weight:600;font-size:14px;">Rating</label>
			<div style="display:flex;gap:8px;margin-bottom:20px;">
				<label style="cursor:pointer;"><input type="radio" name="rating" value="1" /> 1</label>
				<label style="cursor:pointer;"><input type="radio" name="rating" value="2" /> 2</label>
				<label style="cursor:pointer;"><input type="radio" name="rating" value="3" checked /> 3</label>
				<label style="cursor:pointer;"><input type="radio" name="rating" value="4" /> 4</label>
				<label style="cursor:pointer;"><input type="radio" name="rating" value="5" /> 5</label>
			</div>

			<button type="submit" style="background:#5b2d6e;color:#fff;border:none;padding:12px 28px;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;">Submit Notes</button>
		</form>';

		self::render_page('Meeting Notes 📝', $form);
	}

	private static function process_notes_submission(array $data, string $raw_token): void {
		$meeting_id = $data['payload']['meeting_id'] ?? 0;

		$notes_data = [
			'wins'         => sanitize_textarea_field(wp_unslash($_POST['wins'] ?? '')),
			'insights'     => sanitize_textarea_field(wp_unslash($_POST['insights'] ?? '')),
			'action_items' => sanitize_textarea_field(wp_unslash($_POST['action_items'] ?? '')),
			'rating'       => absint($_POST['rating'] ?? 3),
		];

		$result = CBNexus_Meeting_Service::submit_notes($meeting_id, $data['user_id'], $notes_data);

		if ($result['success']) {
			// Consume the token now.
			CBNexus_Token_Service::validate($raw_token);
			self::render_page('Notes Submitted! ✅', '<p>Thanks for sharing your notes — they help the whole group grow.</p>');
		} else {
			self::render_page('Error', '<p>' . esc_html(implode(' ', $result['errors'] ?? ['Something went wrong.'])) . '</p>');
		}
		exit;
	}

	// ─── Action Item Update ────────────────────────────────────────────

	private static function render_action_update_form(array $data, string $token): void {
		$item_id = $data['payload']['item_id'] ?? 0;
		global $wpdb;
		$item = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}cb_circleup_items WHERE id = %d",
			$item_id
		));

		if (!$item) {
			self::render_page('Not Found', '<p>This action item was not found.</p>');
			return;
		}

		$form = '
		<div style="background:#f3eef6;border-radius:10px;padding:16px;margin-bottom:16px;">
			<p style="margin:0;font-weight:600;">✅ ' . esc_html($item->content) . '</p>
			' . ($item->due_date ? '<p style="margin:4px 0 0;font-size:13px;color:#666;">Due: ' . esc_html($item->due_date) . '</p>' : '') . '
		</div>
		<form method="post" style="max-width:500px;">
			<input type="hidden" name="cbnexus_token" value="' . esc_attr($token) . '" />
			<input type="hidden" name="cbnexus_token_action" value="update_action" />

			<label style="display:block;margin:0 0 4px;font-weight:600;font-size:14px;">Status</label>
			<select name="status" style="width:100%;padding:10px;border:1px solid #e0d6e8;border-radius:10px;margin-bottom:16px;">
				<option value="pending" ' . selected($item->status, 'pending', false) . '>Pending</option>
				<option value="in_progress" ' . selected($item->status, 'in_progress', false) . '>In Progress</option>
				<option value="done" ' . selected($item->status, 'done', false) . '>Done ✅</option>
			</select>

			<label style="display:block;margin:0 0 4px;font-weight:600;font-size:14px;">Quick Note (optional)</label>
			<textarea name="note" rows="2" style="width:100%;padding:10px;border:1px solid #e0d6e8;border-radius:10px;font-family:DM Sans,sans-serif;" placeholder="Any update?"></textarea>

			<button type="submit" style="margin-top:16px;background:#5b2d6e;color:#fff;border:none;padding:12px 28px;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;">Update</button>
		</form>';

		self::render_page('Update Action Item', $form);
	}

	private static function process_action_update(array $data): void {
		$item_id = $data['payload']['item_id'] ?? 0;
		$status  = sanitize_key($_POST['status'] ?? 'pending');

		if (!in_array($status, ['pending', 'in_progress', 'done'], true)) {
			$status = 'pending';
		}

		CBNexus_CircleUp_Repository::update_item($item_id, ['status' => $status]);

		self::render_page('Updated! ✅', '<p>Action item marked as <strong>' . esc_html($status) . '</strong>.</p>');
		exit;
	}

	// ─── CircleUp View ─────────────────────────────────────────────────

	private static function render_circleup_view(array $data): void {
		$meeting_id = $data['payload']['meeting_id'] ?? 0;
		$meeting = CBNexus_CircleUp_Repository::get_meeting($meeting_id);
		if (!$meeting || $meeting->status !== 'published') {
			self::render_page('Not Found', '<p>This CircleUp meeting was not found.</p>');
			return;
		}

		$items    = CBNexus_CircleUp_Repository::get_items($meeting_id);
		$approved = array_filter($items, fn($i) => $i->status === 'approved');
		$types    = ['win' => '🏆 Wins', 'insight' => '💡 Insights', 'opportunity' => '🤝 Opportunities', 'action' => '✅ Actions'];

		$html = '<h3 style="margin:0 0 4px;">' . esc_html($meeting->title) . '</h3>';
		$html .= '<p style="color:#666;font-size:14px;">' . esc_html(date_i18n('F j, Y', strtotime($meeting->meeting_date))) . '</p>';

		if ($meeting->curated_summary) {
			$html .= '<p style="line-height:1.7;">' . nl2br(esc_html($meeting->curated_summary)) . '</p>';
		}

		foreach ($types as $type => $label) {
			$typed = array_filter($approved, fn($i) => $i->item_type === $type);
			if (empty($typed)) { continue; }
			$html .= '<h4 style="margin:20px 0 8px;">' . esc_html($label) . '</h4><ul style="padding-left:20px;">';
			foreach ($typed as $item) {
				$html .= '<li>' . esc_html($item->content) . '</li>';
			}
			$html .= '</ul>';
		}

		self::render_page('CircleUp Notes', $html);
	}

	// ─── Forward CircleUp ──────────────────────────────────────────────

	private static function render_forward_form(array $data, string $token): void {
		$meeting_id = $data['payload']['meeting_id'] ?? 0;
		$meeting    = CBNexus_CircleUp_Repository::get_meeting($meeting_id);
		$title      = $meeting ? $meeting->title : 'CircleUp Notes';

		$form = '
		<p>Forward the notes from <strong>' . esc_html($title) . '</strong> to someone outside the group.</p>
		<form method="post" style="max-width:500px;">
			<input type="hidden" name="cbnexus_token" value="' . esc_attr($token) . '" />
			<input type="hidden" name="cbnexus_token_action" value="forward_circleup" />

			<label style="display:block;margin:0 0 4px;font-weight:600;font-size:14px;">Recipient Email</label>
			<input type="email" name="forward_email" required style="width:100%;padding:10px;border:1px solid #e0d6e8;border-radius:10px;margin-bottom:12px;" placeholder="colleague@example.com" />

			<label style="display:block;margin:0 0 4px;font-weight:600;font-size:14px;">Personal Note (optional)</label>
			<textarea name="forward_note" rows="2" style="width:100%;padding:10px;border:1px solid #e0d6e8;border-radius:10px;font-family:DM Sans,sans-serif;" placeholder="Thought you\'d find this interesting..."></textarea>

			<button type="submit" style="margin-top:16px;background:#5b2d6e;color:#fff;border:none;padding:12px 28px;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;">Forward</button>
		</form>';

		self::render_page('Forward Notes 📨', $form);
	}

	private static function process_circleup_forward(array $data): void {
		$meeting_id = $data['payload']['meeting_id'] ?? 0;
		$email      = sanitize_email($_POST['forward_email'] ?? '');
		$note       = sanitize_textarea_field(wp_unslash($_POST['forward_note'] ?? ''));

		if (!is_email($email)) {
			self::render_page('Invalid Email', '<p>Please enter a valid email address.</p>');
			exit;
		}

		$sender = CBNexus_Member_Repository::get_profile($data['user_id']);
		$sender_name = $sender ? $sender['display_name'] : 'A CircleBlast member';

		// Generate a view token for the recipient (30-day, multi-use).
		$view_token = CBNexus_Token_Service::generate(0, 'view_circleup', ['meeting_id' => $meeting_id], 30, true);
		$view_url   = CBNexus_Token_Service::url($view_token);

		CBNexus_Email_Service::send('circleup_forward', $email, [
			'sender_name'  => $sender_name,
			'forward_note' => $note,
			'view_url'     => $view_url,
			'meeting_id'   => $meeting_id,
		], ['recipient_id' => 0, 'related_id' => $meeting_id, 'related_type' => 'circleup_forward']);

		self::render_page('Forwarded! ✅', '<p>Notes sent to <strong>' . esc_html($email) . '</strong>.</p>');
		exit;
	}

	// ─── Quick Share ───────────────────────────────────────────────────

	private static function render_quick_share_form(array $data, string $token): void {
		$form = '
		<p>Share a win, insight, or opportunity with the group.</p>
		<form method="post" style="max-width:500px;">
			<input type="hidden" name="cbnexus_token" value="' . esc_attr($token) . '" />
			<input type="hidden" name="cbnexus_token_action" value="quick_share" />

			<label style="display:block;margin:0 0 4px;font-weight:600;font-size:14px;">Type</label>
			<select name="item_type" style="width:100%;padding:10px;border:1px solid #e0d6e8;border-radius:10px;margin-bottom:12px;">
				<option value="win">🏆 Win</option>
				<option value="insight">💡 Insight</option>
				<option value="opportunity">🤝 Opportunity</option>
			</select>

			<label style="display:block;margin:0 0 4px;font-weight:600;font-size:14px;">What happened?</label>
			<textarea name="content" rows="3" required style="width:100%;padding:10px;border:1px solid #e0d6e8;border-radius:10px;font-family:DM Sans,sans-serif;" placeholder="Share what happened..."></textarea>

			<button type="submit" style="margin-top:16px;background:#5b2d6e;color:#fff;border:none;padding:12px 28px;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;">Share</button>
		</form>';

		self::render_page('Quick Share 💬', $form);
	}

	private static function process_quick_share(array $data): void {
		$type    = sanitize_key($_POST['item_type'] ?? 'win');
		$content = sanitize_textarea_field(wp_unslash($_POST['content'] ?? ''));

		if (empty($content)) {
			self::render_page('Error', '<p>Please enter some content.</p>');
			exit;
		}

		if (!in_array($type, ['win', 'insight', 'opportunity'], true)) {
			$type = 'win';
		}

		$meetings = CBNexus_CircleUp_Repository::get_meetings('published', 1);
		$meeting_id = !empty($meetings) ? (int) $meetings[0]->id : 0;

		if (!$meeting_id) {
			self::render_page('Error', '<p>No active CircleUp meeting to attach this to.</p>');
			exit;
		}

		CBNexus_CircleUp_Repository::insert_items($meeting_id, [[
			'item_type'  => $type,
			'content'    => $content,
			'speaker_id' => $data['user_id'],
			'status'     => 'approved',
		]]);

		self::render_page('Shared! ✅', '<p>Thanks for contributing — your ' . esc_html($type) . ' has been added.</p>');
		exit;
	}

	// ─── Page Renderer ─────────────────────────────────────────────────

	/**
	 * Render a standalone, branded page without requiring WP login or theme.
	 */
	private static function render_page(string $title, string $body): void {
		header('Content-Type: text/html; charset=UTF-8');
		$year = gmdate('Y');
		echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
		<title>' . esc_html($title) . ' — CircleBlast</title>
		<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
		<style>
			*{box-sizing:border-box;margin:0;padding:0;}
			body{font-family:"DM Sans",sans-serif;background:#f3eef6;color:#2a1f33;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
			.cb-page{background:#fff;border-radius:16px;box-shadow:0 1px 3px rgba(42,31,51,.06);max-width:580px;width:100%;padding:36px;text-align:left;}
			.cb-brand{text-align:center;margin-bottom:24px;font-size:13px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:#5b2d6e;}
			.cb-brand span{display:inline-block;width:8px;height:8px;border-radius:50%;background:#c49a3c;margin-right:6px;vertical-align:middle;}
			h2{font-size:22px;font-weight:700;letter-spacing:-0.3px;margin-bottom:14px;}
			p{font-size:15px;line-height:1.7;color:#4a4055;margin-bottom:12px;}
			a{color:#5b2d6e;}
			textarea,input[type="email"],input[type="text"],select{font-family:"DM Sans",sans-serif;font-size:14px;}
			.cb-footer{text-align:center;margin-top:28px;font-size:12px;color:#a094a8;}
		</style></head><body>
		<div class="cb-page">
			<div class="cb-brand"><span></span>CircleBlast</div>
			<h2>' . $title . '</h2>
			' . $body . '
			<div class="cb-footer">&copy; ' . $year . ' CircleBlast</div>
		</div></body></html>';
	}
}
