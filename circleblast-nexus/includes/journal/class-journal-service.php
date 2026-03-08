<?php
/**
 * Journal Service
 *
 * Business logic for member journal entries.
 */

defined('ABSPATH') || exit;

final class CBNexus_Journal_Service {

	/**
	 * Create a new journal entry after validation.
	 *
	 * @param int   $member_id Author user ID.
	 * @param array $data      Raw POST data (entry_type, content, context, entry_date, visibility).
	 * @return array{success:bool, entry_id?:int, errors?:string[]}
	 */
	public static function create(int $member_id, array $data): array {
		$errors = self::validate($data);
		if (!empty($errors)) {
			return ['success' => false, 'errors' => $errors];
		}

		$entry_id = CBNexus_Journal_Repository::create($member_id, $data);
		if (!$entry_id) {
			return ['success' => false, 'errors' => ['Failed to save journal entry.']];
		}

		return ['success' => true, 'entry_id' => $entry_id];
	}

	/**
	 * Delete an entry (only the owner may delete).
	 *
	 * @param int $entry_id  Entry ID.
	 * @param int $member_id Requesting user ID.
	 * @return array{success:bool, errors?:string[]}
	 */
	public static function delete(int $entry_id, int $member_id): array {
		$entry = CBNexus_Journal_Repository::get($entry_id);
		if (!$entry) {
			return ['success' => false, 'errors' => ['Entry not found.']];
		}
		if ((int) $entry->member_id !== $member_id) {
			return ['success' => false, 'errors' => ['You do not own this entry.']];
		}

		$ok = CBNexus_Journal_Repository::delete($entry_id, $member_id);
		return $ok ? ['success' => true] : ['success' => false, 'errors' => ['Delete failed.']];
	}

	// ─── Private ───────────────────────────────────────────────────────

	private static function validate(array $data): array {
		$errors = [];

		$content = trim($data['content'] ?? '');
		if ($content === '') {
			$errors[] = 'Content is required.';
		} elseif (mb_strlen($content) > 2000) {
			$errors[] = 'Content must be 2,000 characters or fewer.';
		}

		if (!empty($data['entry_type']) && !in_array($data['entry_type'], CBNexus_Journal_Repository::TYPES, true)) {
			$errors[] = 'Invalid entry type.';
		}

		if (!empty($data['entry_date'])) {
			$ts = strtotime($data['entry_date']);
			if (!$ts || $ts > strtotime('tomorrow')) {
				$errors[] = 'Entry date cannot be in the future.';
			}
		}

		return $errors;
	}
}
