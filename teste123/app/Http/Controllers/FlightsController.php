<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;

class FlightsController extends BaseController
{
    /* 
     * Realiza a requisição dos voos e depois faz as operações necessárias
     */
    public function get() 
    {
        $client = new Client();
        $request = new \GuzzleHttp\Psr7\Request('GET', 'http://prova.123milhas.net/api/flights');

        $promise = $client->sendAsync($request)->then(function ($response) {
            $voos = json_decode($response->getBody()->getContents(), true);
            $this->agrupaPorTipoETarifa($voos);
        });
        $promise->wait();
    }
    /*
     * Realiza o agrupamento criando um array associativo cujas chaves são 
     * o tipo de voo (ida ou volta) e a tarifa
    */
    public function agrupaPorTipoETarifa ($voos) 
    {
        $tarifas = array();
        $tipos = array();
        foreach($voos as $voo) {
            if(isset($voo['inbound'])) {
                $idaOuVolta = $voo['inbound'] == 0 ? 'Ida' : 'Volta';
                if(isset($tarifas[$voo['fare'].'|'.$idaOuVolta])) {
                    $aux = array(
                        'id' => $voo['id'],
                        'price' => $voo['price']
                    );
                    array_push($tarifas[$voo['fare'].'|'.$idaOuVolta], $aux);
                } else {
                    $aux = array(
                        'id' => $voo['id'],
                        'price' => $voo['price']
                    );
                    $tarifas[$voo['fare'].'|'.$idaOuVolta] = array($aux);
                }
                if(!in_array($voo['fare'], $tipos)) {
                    array_push($tipos, $voo['fare']);
                }
            }
        }
        $this->agrupaIdaEVolta($tarifas, $tipos);
    }
    /*
     * Para cada tipo de tarifa, agrupa as idas e voltas pelo preço,
     * assim terá um vetor com id's que possuem o mesmo preço.
     * depois é feito um crossJoin com as chaves do array de ida e volta
     * com isso, terão todas as possibilidades de ida e volta com o mesmo preço
     */
    private function agrupaIdaEVolta($tarifas, $tipos) 
    {
        $idas = array();
        $voltas = array();
        $grupoFinal = array();
        $id = 0;
        foreach($tipos as $tipo) {
            $idas = $this->agrupar('price', $tarifas[$tipo.'|Ida']) + $idas;
            $voltas = $this->agrupar('price', $tarifas[$tipo.'|Volta']) + $voltas;

            $collection = collect(array_keys($idas));
            $matrix = $collection->crossJoin(array_keys($voltas));
            
            foreach($matrix->all() as $mat) {
                $grupoFinal[] = array(
                    'uniqueId' => $id,
                    'outbound' => $idas[$mat[0]],
                    'inbound' => $voltas[$mat[1]],
                    'totalPrice' => $mat[0] + $mat[1]
                );
                $id++;
            }
        }
        $grupoOrdenado = $this->ordenar($grupoFinal);
    }

    function ordenar($vetor) {
        $price = array();
        foreach ($vetor as $key => $row)
        {
            $price[$key] = $row['totalPrice'];
        }
        array_multisort($price, SORT_ASC, $vetor);
        return $vetor;
    }
    
    /*
     * Função simples de agrupamento
     */
    function agrupar($key, $data) {
        $result = array();
    
        foreach($data as $val) {
            if(array_key_exists($key, $val)){
                if(isset($result[$val[$key]])){
                    array_push($result[$val[$key]], $val['id']);
                } else {
                    $result[$val[$key]][] = $val['id'];
                }
            }else{
                $result[""][] = $val;
            }
        }
    
        return $result;
    }
}
