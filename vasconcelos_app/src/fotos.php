<?php
declare(strict_types=1);

const FOTO_MAX_BYTES = 15 * 1024 * 1024;
const FOTO_LADO_GRANDE = 1600;
const FOTO_LADO_PEQUENO = 640;

function pasta_fotos(int $imovelId, string $tipo = 'imoveis'): string
{
    $tipo = in_array($tipo, ['blog', 'condominios'], true) ? $tipo : 'imoveis';
    $dir = rtrim((string)config('public_dir'), '/') . '/uploads/' . $tipo . '/' . $imovelId;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Não foi possível criar a pasta de fotos.');
    }
    return $dir;
}

/**
 * Recria a imagem do zero (remove metadados e qualquer conteúdo escondido),
 * corrige a rotação do celular e salva duas versões JPEG: grande e pequena.
 * Retorna o nome base do arquivo.
 */
function processar_imagem(int $imovelId, string $origem, string $tipo = 'imoveis'): array
{
    $size = filesize($origem);
    if ($size === false || $size > FOTO_MAX_BYTES) {
        throw new RuntimeException('Foto muito grande (máximo 15 MB).');
    }
    $info = @getimagesize($origem);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        throw new RuntimeException('Formato não aceito. Envie JPG, PNG ou WEBP.');
    }
    if ($info[0] * $info[1] > 40_000_000) {
        throw new RuntimeException('Foto com resolução exagerada.');
    }
    $img = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($origem),
        IMAGETYPE_PNG => @imagecreatefrompng($origem),
        IMAGETYPE_WEBP => @imagecreatefromwebp($origem),
    };
    if (!$img) {
        throw new RuntimeException('Não foi possível ler a foto.');
    }
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($origem);
        $rot = match ((int)($exif['Orientation'] ?? 1)) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
        if ($rot) {
            $r = imagerotate($img, $rot, 0);
            if ($r) { imagedestroy($img); $img = $r; }
        }
    }
    $nome = bin2hex(random_bytes(8));
    $dir = pasta_fotos($imovelId, $tipo);
    $dims = salvar_jpeg($img, $dir . '/' . $nome . '-g.jpg', FOTO_LADO_GRANDE, 82);
    salvar_jpeg($img, $dir . '/' . $nome . '-p.jpg', FOTO_LADO_PEQUENO, 78);
    imagedestroy($img);
    return ['nome' => $nome, 'largura' => $dims[0], 'altura' => $dims[1]];
}

function salvar_jpeg(GdImage $img, string $destino, int $ladoMax, int $qualidade): array
{
    $w = imagesx($img);
    $h = imagesy($img);
    $escala = min(1, $ladoMax / max($w, $h));
    $nw = max(1, (int)round($w * $escala));
    $nh = max(1, (int)round($h * $escala));
    $out = imagecreatetruecolor($nw, $nh);
    $branco = imagecolorallocate($out, 255, 255, 255);
    imagefilledrectangle($out, 0, 0, $nw, $nh, $branco);
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imageinterlace($out, true);
    if (!imagejpeg($out, $destino, $qualidade)) {
        throw new RuntimeException('Falha ao salvar a foto.');
    }
    imagedestroy($out);
    return [$nw, $nh];
}

function proxima_ordem_foto(int $imovelId): int
{
    return (int)q('SELECT COALESCE(MAX(ordem), -1) + 1 FROM fotos WHERE imovel_id = ?', [$imovelId])->fetchColumn();
}

function adicionar_foto_upload(int $imovelId, array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Falha no envio da foto.');
    }
    $r = processar_imagem($imovelId, $file['tmp_name']);
    $ordem = proxima_ordem_foto($imovelId);
    q('INSERT INTO fotos (imovel_id, arquivo, largura, altura, ordem) VALUES (?,?,?,?,?)', [$imovelId, $r['nome'], $r['largura'], $r['altura'], $ordem]);
    $id = (int)db()->lastInsertId();
    return ['id' => $id, 'imovel_id' => $imovelId, 'arquivo' => $r['nome'], 'largura' => $r['largura'], 'altura' => $r['altura'], 'ordem' => $ordem];
}

/** Baixa uma foto de um endereço https (usado na migração). */
function baixar_imagem(string $url): string
{
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new RuntimeException('Endereço de foto inválido.');
    }
    $tmp = tempnam(sys_get_temp_dir(), 'vcf');
    $fh = fopen($tmp, 'wb');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fh,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_USERAGENT => 'VasconcelosMigracao/1.0',
        CURLOPT_NOPROGRESS => false,
        CURLOPT_PROGRESSFUNCTION => fn($ch, $dlTotal, $dl) => $dl > FOTO_MAX_BYTES ? 1 : 0,
    ]);
    $ok = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    fclose($fh);
    if (!$ok || $code !== 200) {
        @unlink($tmp);
        throw new RuntimeException("Download falhou (HTTP $code).");
    }
    return $tmp;
}

function adicionar_foto_remota(int $imovelId, string $url, ?string $legenda = null): void
{
    $tmp = baixar_imagem($url);
    try {
        $r = processar_imagem($imovelId, $tmp);
    } finally {
        @unlink($tmp);
    }
    q('INSERT INTO fotos (imovel_id, arquivo, largura, altura, legenda, ordem) VALUES (?,?,?,?,?,?)',
        [$imovelId, $r['nome'], $r['largura'], $r['altura'], $legenda ? mb_substr($legenda, 0, 120) : null, proxima_ordem_foto($imovelId)]);
}

function apagar_arquivos_foto(array $foto): void
{
    if (empty($foto['arquivo']) || !empty($foto['condominio_foto_id'])) {
        return;
    }
    $base = rtrim((string)config('public_dir'), '/') . '/uploads/imoveis/' . (int)$foto['imovel_id'] . '/' . basename($foto['arquivo']);
    @unlink($base . '-g.jpg');
    @unlink($base . '-p.jpg');
}

function excluir_foto(int $fotoId, int $imovelId): bool
{
    $foto = q('SELECT * FROM fotos WHERE id = ? AND imovel_id = ?', [$fotoId, $imovelId])->fetch();
    if (!$foto || !empty($foto['condominio_foto_id'])) {
        return false; // foto do condomínio: esconder, não apagar
    }
    apagar_arquivos_foto($foto);
    q('DELETE FROM fotos WHERE id = ?', [$fotoId]);
    return true;
}

function reordenar_fotos(int $imovelId, array $ids): void
{
    $st = db()->prepare('UPDATE fotos SET ordem = ? WHERE id = ? AND imovel_id = ?');
    foreach (array_values($ids) as $ordem => $id) {
        $st->execute([$ordem, (int)$id, $imovelId]);
    }
}

function excluir_imovel(int $id): void
{
    foreach (fotos_do_imovel($id) as $f) {
        apagar_arquivos_foto($f);
    }
    $dir = rtrim((string)config('public_dir'), '/') . '/uploads/imoveis/' . $id;
    if (is_dir($dir)) {
        @rmdir($dir);
    }
    q('DELETE FROM imoveis WHERE id = ?', [$id]);
}

/** Mostra/esconde no anúncio uma foto do condomínio. Devolve o novo estado (true = escondida). */
function alternar_foto_oculta(int $fotoId, int $imovelId): ?bool
{
    $foto = q('SELECT * FROM fotos WHERE id = ? AND imovel_id = ? AND condominio_foto_id IS NOT NULL', [$fotoId, $imovelId])->fetch();
    if (!$foto) {
        return null;
    }
    $nova = $foto['oculta'] ? 0 : 1;
    q('UPDATE fotos SET oculta = ? WHERE id = ?', [$nova, $fotoId]);
    return (bool)$nova;
}


/* ---------- Fotos enviadas antes de salvar um imóvel novo ----------
 * Ficam em storage/fotos_temp/<token>/ (fora da pasta pública) até o imóvel ser salvo.
 * Pastas esquecidas com mais de 2 dias são apagadas sozinhas.
 */

function token_fotos_temp_valido(string $token): bool
{
    return (bool)preg_match('/^[a-f0-9]{32}$/', $token);
}

function nome_foto_temp_valido(string $nome): bool
{
    return (bool)preg_match('/^\d{10,20}-[a-f0-9]{8}$/', $nome);
}

function pasta_fotos_temp(?string $token = null): string
{
    $base = APP_DIR . '/storage/fotos_temp';
    return $token === null ? $base : $base . '/' . $token;
}

function limpar_fotos_temp_antigas(): void
{
    foreach (glob(pasta_fotos_temp() . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
        if (filemtime($dir) < time() - 2 * 86400) {
            foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
            @rmdir($dir);
        }
    }
}

function guardar_foto_temp(string $token, array $file): string
{
    if (!token_fotos_temp_valido($token)) {
        throw new RuntimeException('Recarregue a página e tente de novo.');
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Falha no envio da foto.');
    }
    if ((int)$file['size'] > FOTO_MAX_BYTES) {
        throw new RuntimeException('Foto muito grande (máximo 15 MB).');
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        throw new RuntimeException('Formato não aceito. Envie JPG, PNG ou WEBP.');
    }
    if (random_int(1, 20) === 1) limpar_fotos_temp_antigas();
    $dir = pasta_fotos_temp($token);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Não foi possível guardar a foto.');
    }
    if (count(glob($dir . '/*') ?: []) >= 80) {
        throw new RuntimeException('Limite de 80 fotos antes de salvar. Salve o imóvel e envie o resto.');
    }
    $nome = sprintf('%d-%s', (int)(microtime(true) * 1000), bin2hex(random_bytes(4)));
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $nome)) {
        throw new RuntimeException('Não foi possível guardar a foto.');
    }
    return $nome;
}

function caminho_foto_temp(string $token, string $nome): ?string
{
    if (!token_fotos_temp_valido($token) || !nome_foto_temp_valido($nome)) return null;
    $f = pasta_fotos_temp($token) . '/' . $nome;
    return is_file($f) ? $f : null;
}

function excluir_foto_temp(string $token, string $nome): void
{
    if ($f = caminho_foto_temp($token, $nome)) @unlink($f);
}

/** Leva as fotos guardadas para o imóvel recém-criado, na ordem escolhida. @return array{0:int,1:int} [enviadas, falhas] */
function anexar_fotos_temp(int $imovelId, string $token, array $nomes): array
{
    if (!token_fotos_temp_valido($token)) return [0, 0];
    $ok = 0;
    $falhas = 0;
    foreach (array_unique(array_map('strval', $nomes)) as $nome) {
        $f = caminho_foto_temp($token, $nome);
        if (!$f) continue;
        try {
            $r = processar_imagem($imovelId, $f);
            q('INSERT INTO fotos (imovel_id, arquivo, largura, altura, ordem) VALUES (?,?,?,?,?)',
                [$imovelId, $r['nome'], $r['largura'], $r['altura'], proxima_ordem_foto($imovelId)]);
            $ok++;
        } catch (Throwable $e) {
            $falhas++;
        }
    }
    $dir = pasta_fotos_temp($token);
    foreach (glob($dir . '/*') ?: [] as $arq) @unlink($arq);
    @rmdir($dir);
    return [$ok, $falhas];
}
