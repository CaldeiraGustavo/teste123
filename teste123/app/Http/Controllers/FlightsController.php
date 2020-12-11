<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use GuzzleHttp\Client;

class FlightsController extends BaseController
{
    public function get(Request $request) 
    {
        $client = new Client();
        $request = new \GuzzleHttp\Psr7\Request('GET', 'http://prova.123milhas.net/api/flights');

        $promise = $client->sendAsync($request)->then(function ($response) {
            $voos = json_decode($response->getBody()->getContents(), true);
            $this->agrupaSabosta($voos);
        });
        $promise->wait();
    }

    public function agrupaSabosta ($voos) 
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

    private function agrupaIdaEVolta($tarifas, $tipos) 
    {
        $grupoIda = array();
        $grupoVolta = array();
        foreach($tipos as $tipo) {
            $grupoIda = $this->group_by('price', $tarifas[$tipo.'|Ida']) + $grupoIda;
            $grupoVolta = $this->group_by('price', $tarifas[$tipo.'|Volta']) + $grupoVolta;
        }
        var_dump(array_keys($grupoIda));
    }

    function group_by($key, $data) {
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
