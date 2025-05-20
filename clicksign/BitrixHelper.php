<?php
//oi
class BitrixHelper
{
    private $webhook;

    public function __construct($webhookBase)
    {
        $this->webhook = rtrim($webhookBase, '/');
    }

    public function getDeal($id)
    {
        return $this->call('crm.deal.get', ['id' => $id]);
    }

    public function getSpaItem($spaId, $itemId)
    {
        return $this->call('crm.item.get', [
            'entityTypeId' => $spaId,
            'id' => $itemId
        ]);
    }

    public function getContact($id)
    {
        return $this->call('crm.contact.get', ['id' => $id]);
    }

    public function getCompany($id)
    {
        return $this->call('crm.company.get', ['id' => $id]);
    }

    public function getMultipleContacts($idList)
    {
        $ids = array_map('trim', explode(',', $idList));
        $contatos = [];

        foreach ($ids as $id) {
            $dados = $this->getContact($id);
            if (!empty($dados['result'])) {
                $contatos[] = $dados['result'];
            }
        }

        return $contatos;
    }

    public function updateDealField($id, $fields)
    {
        return $this->call('crm.deal.update', [
            'id' => $id,
            'fields' => $fields
        ]);
    }

    public function updateSpaItemField($spaId, $itemId, $fields)
    {
        return $this->call('crm.item.update', [
            'entityTypeId' => $spaId,
            'id' => $itemId,
            'fields' => $fields
        ]);
    }

    public function addCommentToDeal($dealId, $message)
    {
        return $this->call('crm.timeline.comment.add', [
            'fields' => [
                'ENTITY_ID' => $dealId,
                'ENTITY_TYPE' => 'deal',
                'COMMENT' => $message
            ]
        ]);
    }

 private function call($method, $params)
{
    file_put_contents('log_debug_exec.txt', "Executando método: $method" . PHP_EOL, FILE_APPEND);
    $url = $this->webhook . '/' . $method;

    if ($method === 'crm.contact.get') {
        $url .= '?' . http_build_query($params);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    } else {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }

    $result = curl_exec($ch);
    curl_close($ch);

    file_put_contents('log_curl_raw.txt', "[$method] => " . json_encode($params) . PHP_EOL . $result . PHP_EOL, FILE_APPEND);

    return json_decode($result, true);
}
}