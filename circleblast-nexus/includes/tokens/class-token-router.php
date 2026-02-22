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

					if ($meeting && $meeting->source === 'auto' && in_array($meeting->status, ['suggested', 'pending'])) {
						// Auto-matched: use two-accept flow.
						$result = CBNexus_Meeting_Service::accept_suggestion($mid, $consumed['user_id']);
						$other = CBNexus_Meeting_Repository::get_other_member(
							CBNexus_Meeting_Repository::get($mid), $consumed['user_id']
						);
						$other_profile = CBNexus_Member_Repository::get_profile($other);
						$name = $other_profile ? $other_profile['display_name'] : 'your match';

						if (($result['state'] ?? '') === 'waiting_for_other') {
							self::render_page('You\'re In! ✅', '<p>You\'ve accepted the 1:1 with <strong>' . esc_html($name) . '</strong>.</p><p>We\'ll let you know as soon as they confirm too.</p>');
						} else {
							self::render_page('Meeting Confirmed! 🎉', '<p>Both you and <strong>' . esc_html($name) . '</strong> have accepted! Coordinate a time that works and enjoy the conversation.</p>');
						}
					} else {
						// Manual meeting: direct accept (existing behavior).
						CBNexus_Meeting_Service::accept($mid, $consumed['user_id']);
						$other = CBNexus_Meeting_Repository::get_other_member(
							CBNexus_Meeting_Repository::get($mid), $consumed['user_id']
						);
						$other_profile = CBNexus_Member_Repository::get_profile($other);
						$name = $other_profile ? $other_profile['display_name'] : 'your match';
						self::render_page('Meeting Accepted! ✅', '<p>You\'ve accepted the 1:1 with <strong>' . esc_html($name) . '</strong>.</p><p>Coordinate a time that works for both of you.</p>');
					}
				}
				exit;

			case 'decline_meeting':
				$consumed = CBNexus_Token_Service::validate($raw_token);
				if ($consumed) {
					$mid = $consumed['payload']['meeting_id'] ?? 0;
					$meeting = CBNexus_Meeting_Repository::get($mid);

					// For suggested meetings, allow direct decline without intermediate transition.
					if ($meeting && $meeting->status === 'suggested') {
						CBNexus_Meeting_Repository::update($mid, ['status' => 'declined']);
						CBNexus_Meeting_Repository::record_response($mid, $consumed['user_id'], 'declined', '');
					} else {
						CBNexus_Meeting_Service::decline($mid, $consumed['user_id']);
					}
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

			case 'approve_event':
				$consumed = CBNexus_Token_Service::validate($raw_token);
				if ($consumed) {
					$eid = $consumed['payload']['event_id'] ?? 0;
					$event = CBNexus_Event_Repository::get($eid);
					if ($event && $event->status === 'pending') {
						CBNexus_Event_Service::approve($eid, $consumed['user_id']);
						self::render_page('Event Approved! ✅', '<p>The event <strong>' . esc_html($event->title) . '</strong> has been approved and is now visible to all members.</p><p>The organizer has been notified.</p>');
					} elseif ($event && $event->status === 'approved') {
						self::render_page('Already Approved', '<p><strong>' . esc_html($event->title) . '</strong> was already approved — no action needed.</p>');
					} elseif ($event && $event->status === 'denied') {
						self::render_page('Event Was Denied', '<p>This event was already denied by another admin. If you want to approve it, please use the admin portal.</p>');
					} else {
						self::render_page('Event Not Found', '<p>This event could not be found.</p>');
					}
				}
				exit;

			case 'deny_event':
				$consumed = CBNexus_Token_Service::validate($raw_token);
				if ($consumed) {
					$eid = $consumed['payload']['event_id'] ?? 0;
					$event = CBNexus_Event_Repository::get($eid);
					if ($event && $event->status === 'pending') {
						CBNexus_Event_Service::deny($eid, $consumed['user_id']);
						self::render_page('Event Denied', '<p>The event <strong>' . esc_html($event->title) . '</strong> has been denied.</p><p>The organizer has been notified.</p>');
					} elseif ($event && $event->status === 'denied') {
						self::render_page('Already Denied', '<p>This event was already denied — no action needed.</p>');
					} elseif ($event && $event->status === 'approved') {
						self::render_page('Event Was Approved', '<p>This event was already approved by another admin. If you want to reverse this, please use the admin portal.</p>');
					} else {
						self::render_page('Event Not Found', '<p>This event could not be found.</p>');
					}
				}
				exit;

			case 'visit_feedback':
				self::handle_visit_feedback($raw_token, $data);
				exit;

			case 'manage_preferences':
				if ($_SERVER['REQUEST_METHOD'] === 'POST') {
					self::process_preferences_form($data, $raw_token);
				} else {
					self::render_preferences_form($data, $raw_token);
				}
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

			case 'manage_preferences':
				self::process_preferences_form($data, $raw_token);
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

	// ─── Visit Feedback (Recruitment) ─────────────────────────────────

	private static function handle_visit_feedback(string $raw_token, array $peeked): void {
		$candidate_id = $peeked['payload']['candidate_id'] ?? 0;
		$answer       = $peeked['payload']['answer'] ?? '';

		// Consume the token (single-use).
		$consumed = CBNexus_Token_Service::validate($raw_token);
		if (!$consumed) {
			self::render_page('Already Answered', '<p>Looks like you\'ve already responded — thank you!</p><p><a href="' . esc_url(home_url()) . '">Visit CircleBlast →</a></p>');
			exit;
		}

		$labels = [
			'yes'   => 'Yes, I\'m in!',
			'maybe' => 'Tell me more',
			'later' => 'Not right now',
			'no'    => 'Not for me',
		];
		$label = $labels[$answer] ?? $answer;

		// Store the response.
		update_option('cbnexus_visit_feedback_' . $candidate_id, [
			'answer'      => $answer,
			'label'       => $label,
			'answered_at' => gmdate('Y-m-d H:i:s'),
		], false);

		if (class_exists('CBNexus_Logger')) {
			CBNexus_Logger::info('Visit feedback received.', [
				'candidate_id' => $candidate_id,
				'answer'       => $label,
			]);
		}

		// ── Notify the referrer with the response + suggested actions ──
		self::notify_referrer_of_feedback($candidate_id, $answer, $label);

		// Friendly confirmation.
		$messages = [
			'yes'   => '<p>🎉 That\'s great to hear! Someone from CircleBlast will be in touch soon about next steps.</p>',
			'maybe' => '<p>Thanks! We\'ll have someone reach out with more details about what membership looks like.</p>',
			'later' => '<p>No problem at all — the door is always open. Thanks for letting us know!</p>',
			'no'    => '<p>We appreciate your honesty. Thanks for taking the time to visit us!</p>',
		];
		$body = ($messages[$answer] ?? '<p>Thanks for your feedback!</p>')
			. '<p style="margin-top:16px;"><a href="' . esc_url(home_url()) . '" style="color:#5b2d6e;">Visit CircleBlast →</a></p>';

		self::render_page('Thanks for Your Feedback!', $body);
		exit;
	}

	/**
	 * Notify the referring member that their candidate responded to the visit survey.
	 * Includes tailored action steps based on the response.
	 */
	private static function notify_referrer_of_feedback(int $candidate_id, string $answer, string $label): void {
		global $wpdb;
		$table = $wpdb->prefix . 'cb_candidates';
		$candidate = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $candidate_id));
		if (!$candidate || !$candidate->referrer_id) { return; }

		$referrer = get_userdata($candidate->referrer_id);
		if (!$referrer) { return; }

		// Build action block based on response.
		$action_blocks = [
			'yes' => '<div style="background:#f0fdf4;border-left:3px solid #16a34a;padding:14px 18px;margin:16px 0;font-size:14px;color:#166534;">'
				. '<strong>🎉 Great news!</strong> They\'re ready to join. Here\'s what to do next:'
				. '<ul style="margin:8px 0 0 16px;padding:0;line-height:1.8;">'
				. '<li>Reach out to congratulate them and answer any remaining questions</li>'
				. '<li>Let them know the Council will be in touch about next steps and membership details</li>'
				. '<li>If you haven\'t already, gather their feedback on overall impression and connections made</li>'
				. '</ul></div>',

			'maybe' => '<div style="background:#eff6ff;border-left:3px solid #2563eb;padding:14px 18px;margin:16px 0;font-size:14px;color:#1e40af;">'
				. '<strong>🤔 They\'re interested but want more info.</strong> Here\'s how to help:'
				. '<ul style="margin:8px 0 0 16px;padding:0;line-height:1.8;">'
				. '<li>Schedule a casual follow-up conversation — coffee, call, or text</li>'
				. '<li>Share what you personally get out of CircleBlast and why you referred them</li>'
				. '<li>Answer questions about commitment, cost, meeting format, and member expectations</li>'
				. '<li>Ask about their overall impression and whether they connected with anyone specifically</li>'
				. '</ul></div>',

			'later' => '<div style="background:#fefce8;border-left:3px solid #ca8a04;padding:14px 18px;margin:16px 0;font-size:14px;color:#854d0e;">'
				. '<strong>⏳ Not right now — but the door is open.</strong> Suggested approach:'
				. '<ul style="margin:8px 0 0 16px;padding:0;line-height:1.8;">'
				. '<li>Reach out casually — acknowledge the timing may not be right and there\'s no pressure</li>'
				. '<li>Ask what would need to change for them to reconsider (timing, format, cost, etc.)</li>'
				. '<li>Offer to invite them to a future meeting when they\'re ready</li>'
				. '<li>Keep the relationship warm — they may be a great fit down the road</li>'
				. '</ul></div>',

			'no' => '<div style="background:#fef2f2;border-left:3px solid #dc2626;padding:14px 18px;margin:16px 0;font-size:14px;color:#991b1b;">'
				. '<strong>Thank them for their time.</strong> Here\'s what we suggest:'
				. '<ul style="margin:8px 0 0 16px;padding:0;line-height:1.8;">'
				. '<li>A quick message thanking them for visiting — keep the relationship positive</li>'
				. '<li>If comfortable, ask what didn\'t resonate — their feedback helps us improve</li>'
				. '<li>No hard feelings — not every group is the right fit for everyone</li>'
				. '</ul></div>',
		];

		$action_block = $action_blocks[$answer] ?? '';

		if (class_exists('CBNexus_Email_Service')) {
			CBNexus_Email_Service::send('recruit_feedback_referrer', $referrer->user_email, [
				'referrer_name'  => $referrer->display_name,
				'candidate_name' => $candidate->name,
				'feedback_label' => $label,
				'action_block'   => $action_block,
			], [
				'recipient_id' => $referrer->ID,
				'related_type' => 'recruitment_feedback',
				'related_id'   => $candidate->id,
			]);
		}
	}

	// ─── Manage Preferences ─────────────────────────────────────────────

	private static function render_preferences_form(array $data, string $token): void {
		$user_id = $data['user_id'];
		$freq      = get_user_meta($user_id, 'cb_matching_frequency', true) ?: 'monthly';
		$digest    = get_user_meta($user_id, 'cb_email_digest', true) ?: 'yes';
		$reminders = get_user_meta($user_id, 'cb_email_reminders', true) ?: 'yes';

		$form = '
		<p>Update your email preferences below.</p>
		<form method="post" style="max-width:500px;">
			<input type="hidden" name="cbnexus_token" value="' . esc_attr($token) . '" />
			<input type="hidden" name="cbnexus_token_action" value="manage_preferences" />

			<label style="display:block;margin:16px 0 4px;font-weight:600;font-size:14px;">1:1 Matching Frequency</label>
			<select name="cb_matching_frequency" style="width:100%;padding:10px;border:1px solid #e0d6e8;border-radius:10px;margin-bottom:4px;font-family:DM Sans,sans-serif;">
				<option value="monthly" ' . selected($freq, 'monthly', false) . '>Monthly</option>
				<option value="quarterly" ' . selected($freq, 'quarterly', false) . '>Quarterly</option>
				<option value="paused" ' . selected($freq, 'paused', false) . '>Paused</option>
			</select>
			<p style="font-size:12px;color:#888;margin:0 0 12px;">How often you\'d like to be paired. "Paused" = no new suggestions.</p>

			<label style="display:block;margin:0 0 4px;font-weight:600;font-size:14px;">Events Digest</label>
			<select name="cb_email_digest" style="width:100%;padding:10px;border:1px solid #e0d6e8;border-radius:10px;margin-bottom:4px;font-family:DM Sans,sans-serif;">
				<option value="yes" ' . selected($digest, 'yes', false) . '>Yes</option>
				<option value="no" ' . selected($digest, 'no', false) . '>No</option>
			</select>
			<p style="font-size:12px;color:#888;margin:0 0 12px;">Weekly email with upcoming events.</p>

			<label style="display:block;margin:0 0 4px;font-weight:600;font-size:14px;">Reminder Emails</label>
			<select name="cb_email_reminders" style="width:100%;padding:10px;border:1px solid #e0d6e8;border-radius:10px;margin-bottom:12px;font-family:DM Sans,sans-serif;">
				<option value="yes" ' . selected($reminders, 'yes', false) . '>Yes</option>
				<option value="no" ' . selected($reminders, 'no', false) . '>No</option>
			</select>
			<p style="font-size:12px;color:#888;margin:0 0 16px;">Follow-up nudges for unanswered suggestions and meeting reminders.</p>

			<button type="submit" style="background:#5b2d6e;color:#fff;border:none;padding:12px 28px;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;">Save Preferences</button>
		</form>';

		self::render_page('Email Preferences 📧', $form);
	}

	private static function process_preferences_form(array $data, string $raw_token): void {
		$user_id = $data['user_id'];

		$freq      = sanitize_key($_POST['cb_matching_frequency'] ?? 'monthly');
		$digest    = sanitize_key($_POST['cb_email_digest'] ?? 'yes');
		$reminders = sanitize_key($_POST['cb_email_reminders'] ?? 'yes');

		// Validate values.
		if (!in_array($freq, ['monthly', 'quarterly', 'paused'], true)) { $freq = 'monthly'; }
		if (!in_array($digest, ['yes', 'no'], true)) { $digest = 'yes'; }
		if (!in_array($reminders, ['yes', 'no'], true)) { $reminders = 'yes'; }

		update_user_meta($user_id, 'cb_matching_frequency', $freq);
		update_user_meta($user_id, 'cb_email_digest', $digest);
		update_user_meta($user_id, 'cb_email_reminders', $reminders);

		self::render_page('Preferences Updated ✅', '<p>Your email preferences have been saved.</p>');
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