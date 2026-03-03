<?php
declare(strict_types=1);

class QualityScorer
{
    private const INTENT_KEYWORDS = [
        'urgent', 'help', 'problem', 'issue', 'need', 'want',
        'looking', 'marriage', 'job', 'career', 'love',
    ];

    public static function score(array $lead): int
    {
        $score = 0;

        // Completeness (max 50 points)
        if (!empty($lead['name']))     $score += 10;
        if (!empty($lead['phone']))    $score += 15;
        if (!empty($lead['category'])) $score += 10;
        if (!empty($lead['city']))     $score += 5;
        if (!empty($lead['email']))    $score += 5;
        if (!empty($lead['notes']))    $score += 5;

        // Recency (max 30 points) – based on created_at
        if (!empty($lead['created_at'])) {
            $age = time() - strtotime($lead['created_at']);
            if ($age < 3600)   $score += 30;   // < 1 hour
            elseif ($age < 86400)  $score += 20;   // < 1 day
            elseif ($age < 604800) $score += 10;   // < 1 week
        }

        // Intent keywords in notes (max 20 points)
        if (!empty($lead['notes'])) {
            $notesLower = strtolower($lead['notes']);
            $matches    = 0;
            foreach (self::INTENT_KEYWORDS as $kw) {
                if (str_contains($notesLower, $kw)) {
                    $matches++;
                }
            }
            $score += min(20, $matches * 5);
        }

        // Budget provided (10 points)
        if (!empty($lead['budget_range'])) $score += 10;

        return min(100, $score);
    }

    public static function badge(int $score): string
    {
        if ($score >= 80) return 'Premium';
        if ($score >= 60) return 'Verified';
        return 'Basic';
    }

    public static function duplicateHash(string $phone, string $email = ''): string
    {
        $normalizedPhone = preg_replace('/[^0-9]/', '', $phone);
        $normalizedEmail = strtolower(trim($email));
        return hash('sha256', $normalizedPhone . $normalizedEmail);
    }
}
