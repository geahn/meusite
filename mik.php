<?php
// update-feed.php
$secret = 'SEU_TOKEN_SECRET_AQUI';
if ($_GET['key'] !== $secret) {
  http_response_code(403);
  echo 'Acesso negado';
  exit;
}

// Recebe os dados enviados via POST (JSON)
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['title'], $input['description'], $input['pubDate'], $input['url'], $input['length'], $input['duration'])) {
  http_response_code(400);
  echo 'Parâmetros inválidos';
  exit;
}

$xmlFile = __DIR__ . '/mik.xml';

// Verifica se o arquivo existe
if (!file_exists($xmlFile)) {
  http_response_code(500);
  echo 'mik.xml não encontrado';
  exit;
}

// Verifica se o arquivo é gravável
if (!is_writable($xmlFile)) {
  http_response_code(500);
  echo 'mik.xml não é gravável - verifique as permissões';
  exit;
}

// Carrega o XML existente
$xml = simplexml_load_file($xmlFile);
if ($xml === false) {
  http_response_code(500);
  echo 'Erro ao carregar o XML';
  exit;
}

// Converte para DOMDocument para melhor manipulação
$dom = new DOMDocument('1.0', 'UTF-8');
$dom->formatOutput = true;
$dom->preserveWhiteSpace = false;

// Carrega o XML no DOMDocument
if (!$dom->loadXML($xml->asXML())) {
  http_response_code(500);
  echo 'Erro ao processar o XML';
  exit;
}

// Procura pelo elemento channel
$xpath = new DOMXPath($dom);
$channels = $xpath->query('//channel');

if ($channels->length === 0) {
  http_response_code(500);
  echo 'Elemento channel não encontrado no XML';
  exit;
}

$channel = $channels->item(0);

// Adiciona namespace do iTunes se não existir
$root = $dom->documentElement;
if (!$root->hasAttribute('xmlns:itunes')) {
  $root->setAttribute('xmlns:itunes', 'http://www.itunes.com/dtds/podcast-1.0.dtd');
}

// Cria o novo item
$item = $dom->createElement('item');

// Adiciona elementos com escape de caracteres especiais
$title = $dom->createElement('title');
$title->appendChild($dom->createTextNode($input['title']));
$item->appendChild($title);

$description = $dom->createElement('description');
$description->appendChild($dom->createCDATASection($input['description']));
$item->appendChild($description);

$pubDate = $dom->createElement('pubDate');
$pubDate->appendChild($dom->createTextNode($input['pubDate']));
$item->appendChild($pubDate);

// Cria o enclosure
$enclosure = $dom->createElement('enclosure');
$enclosure->setAttribute('url', $input['url']);
$enclosure->setAttribute('length', $input['length']);
$enclosure->setAttribute('type', 'audio/wav');
$item->appendChild($enclosure);

// Adiciona GUID
$guid = $dom->createElement('guid');
$guid->appendChild($dom->createTextNode($input['url']));
$item->appendChild($guid);

// Adiciona duração do iTunes
$itunesDuration = $dom->createElement('itunes:duration');
$itunesDuration->appendChild($dom->createTextNode($input['duration']));
$item->appendChild($itunesDuration);

// Encontra o primeiro item existente para inserir antes dele
$existingItems = $xpath->query('//channel/item');
if ($existingItems->length > 0) {
  $firstItem = $existingItems->item(0);
  $channel->insertBefore($item, $firstItem);
} else {
  $channel->appendChild($item);
}

// Salva o XML de volta
$xmlContent = $dom->saveXML();
if (file_put_contents($xmlFile, $xmlContent) === false) {
  http_response_code(500);
  echo 'Erro ao salvar o arquivo XML';
  exit;
}

echo 'Item adicionado com sucesso!';
?>