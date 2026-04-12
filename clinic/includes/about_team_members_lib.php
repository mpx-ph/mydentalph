<?php
declare(strict_types=1);

/**
 * About page team: JSON list (`about_team_members_json`) or legacy doctor1–3 keys.
 *
 * @return list<array{title: string, name: string, bio: string, tags: string, image: string}>
 */
function clinic_about_team_members_for_display(array $clinic): array
{
    $raw = trim((string) ($clinic['about_team_members_json'] ?? ''));
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $out = [];
            foreach ($decoded as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $title = trim((string) ($row['title'] ?? ''));
                $name = trim((string) ($row['name'] ?? ''));
                $bio = trim((string) ($row['bio'] ?? ''));
                $tags = trim((string) ($row['tags'] ?? ''));
                $image = trim((string) ($row['image'] ?? ''));
                if ($name === '' && $title === '' && $bio === '' && $image === '' && $tags === '') {
                    continue;
                }
                $out[] = [
                    'title' => $title,
                    'name' => $name,
                    'bio' => $bio,
                    'tags' => $tags,
                    'image' => $image,
                ];
            }
            return $out;
        }
    }

    $legacy = [];
    $slots = [
        ['about_team_doctor1_title', 'about_team_doctor1_name', 'about_team_doctor1_bio', 'about_team_doctor1_tags', 'about_team_doctor1_image'],
        ['about_team_doctor2_title', 'about_team_doctor2_name', 'about_team_doctor2_bio', 'about_team_doctor2_tags', 'about_team_doctor2_image'],
        ['about_team_doctor3_title', 'about_team_doctor3_name', 'about_team_doctor3_bio', 'about_team_doctor3_tags', 'about_team_doctor3_image'],
    ];
    foreach ($slots as $i => $keys) {
        [$tk, $nk, $bk, $tagk, $ik] = $keys;
        $name = trim((string) ($clinic[$nk] ?? ''));
        if ($i === 2 && $name === '') {
            break;
        }
        $legacy[] = [
            'title' => trim((string) ($clinic[$tk] ?? '')),
            'name' => $name,
            'bio' => trim((string) ($clinic[$bk] ?? '')),
            'tags' => trim((string) ($clinic[$tagk] ?? '')),
            'image' => trim((string) ($clinic[$ik] ?? '')),
        ];
    }
    return $legacy;
}

/**
 * Initial team rows for the site builder editor (same rules as display, never empty default for brand-new tenants with schema defaults).
 *
 * @param array<string, string> $site_opts
 * @return list<array{title: string, name: string, bio: string, tags: string, image: string}>
 */
function clinic_about_team_members_bootstrap_rows(array $site_opts): array
{
    $rows = clinic_about_team_members_for_display($site_opts);
    return $rows;
}
