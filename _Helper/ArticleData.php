<?php

final class ArticleData
{
    public static function fields(object $input): array
    {
        $fields = [];
        foreach (['title'=>255, 'slug'=>255, 'category_tag'=>50, 'summary'=>500] as $key=>$max) {
            if (!isset($input->$key) || !is_string($input->$key) || trim($input->$key) === '') {
                throw new RuntimeException($key . ' wajib berupa teks tidak kosong.', 400);
            }
            $value = trim($input->$key);
            if (preg_match_all('/./us', $value) > $max) {
                throw new RuntimeException($key . ' maksimal ' . $max . ' karakter.', 400);
            }
            $fields[$key] = $value;
        }
        if (!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $fields['slug'])) {
            throw new RuntimeException('Slug harus berupa huruf kecil/angka yang dipisahkan tanda hubung.', 400);
        }
        if (property_exists($input, 'status')) {
            if (!in_array($input->status, ['published', 'draft'], true)) {
                throw new RuntimeException('Status harus published atau draft.', 400);
            }
            $fields['status'] = $input->status;
        }
        return $fields;
    }

    public static function id($id): int
    {
        if (!is_int($id) || $id < 1 || $id > 4294967295) {
            throw new RuntimeException('Id harus integer positif sampai 4294967295.', 400);
        }
        return $id;
    }

    /** Validasi sebelum upload; null berarti relasi tidak dikirim. */
    public static function tagInput(object $input): ?array
    {
        if (!property_exists($input, 'tag_ids') && !property_exists($input, 'tags')) return null;
        $result = ['ids'=>[], 'new'=>[]];
        foreach (['tag_ids', 'tags'] as $key) {
            if (property_exists($input, $key) && (!is_array($input->$key) || count($input->$key) > 50)) {
                throw new RuntimeException($key . ' harus array dengan maksimal 50 elemen.', 400);
            }
        }
        foreach ($input->tag_ids ?? [] as $id) $result['ids'][] = self::id($id);
        foreach ($input->tags ?? [] as $tag) {
            if (!is_object($tag) || array_diff(array_keys(get_object_vars($tag)), ['name','slug'])) {
                throw new RuntimeException('Tag harus objek berisi name dan slug.', 400);
            }
            foreach (['name','slug'] as $key) {
                if (!isset($tag->$key) || !is_string($tag->$key) || trim($tag->$key) === ''
                    || preg_match_all('/./us', trim($tag->$key)) > 50) {
                    throw new RuntimeException('Name dan slug tag wajib berupa teks maksimal 50 karakter.', 400);
                }
            }
            $slug = trim($tag->slug);
            if (!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug)) {
                throw new RuntimeException('Slug tag tidak valid.', 400);
            }
            $result['new'][] = ['name'=>trim($tag->name), 'slug'=>$slug];
        }
        return $result;
    }

    /** Pemanggil menjalankan transaksi; tidak mengubah master tag yang sudah ada. */
    public static function syncTags(PDO $pdo, int $articleId, ?array $input): void
    {
        if ($input === null) return;
        $ids = [];
        $findId = $pdo->prepare('SELECT id FROM tags WHERE id = ? FOR UPDATE');
        foreach (array_unique($input['ids']) as $id) {
            $findId->execute([$id]);
            if ($findId->fetchColumn() === false) throw new RuntimeException('Tag id ' . $id . ' tidak ditemukan.', 400);
            $ids[] = $id;
        }
        foreach ($input['new'] as $tag) {
            $stmt = $pdo->prepare('SELECT id, (name = :match_name AND slug = :match_slug) AS matches_pair FROM tags WHERE name = :name OR slug = :slug FOR UPDATE');
            $stmt->execute(['match_name'=>$tag['name'], 'match_slug'=>$tag['slug']] + $tag);
            $found = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($found) {
                if (count($found) !== 1 || !(int) $found[0]['matches_pair']) {
                    throw new RuntimeException('Nama atau slug tag sudah digunakan oleh tag berbeda.', 409);
                }
                $ids[] = (int) $found[0]['id'];
            } else {
                $stmt = $pdo->prepare('INSERT INTO tags (name, slug) VALUES (:name, :slug)');
                $stmt->execute($tag);
                $ids[] = (int) $pdo->lastInsertId();
            }
        }
        $pdo->prepare('DELETE FROM article_tag_map WHERE article_id = ?')->execute([$articleId]);
        $insert = $pdo->prepare('INSERT INTO article_tag_map (article_id, tag_id) VALUES (?, ?)');
        foreach (array_unique($ids) as $id) $insert->execute([$articleId, $id]);
    }

    public static function attachTags(PDO $pdo, array $articles): array
    {
        if (!$articles) return [];
        $ids = array_column($articles, 'id');
        $stmt = $pdo->prepare('SELECT m.article_id, t.id, t.name, t.slug FROM article_tag_map m JOIN tags t ON t.id = m.tag_id WHERE m.article_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY t.name, t.id');
        $stmt->execute($ids);
        $tags = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $tags[$row['article_id']][] = ['id'=>(int) $row['id'], 'name'=>$row['name'], 'slug'=>$row['slug']];
        }
        foreach ($articles as &$article) $article['tags'] = $tags[$article['id']] ?? [];
        unset($article);
        return $articles;
    }
}
