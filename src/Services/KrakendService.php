<?php


namespace App\Services;


use http\Exception\RuntimeException;
use Symfony\Component\Process\Process as Process;
use Illuminate\Support\Facades\Artisan;
use Throwable;
use Session;
use Exception;

class KrakendService

{
    public function generateKrakendJson() :string{
        try
        {
            $generate_docs = $this->generateScribeDocumentation();
            $convert_docs = $this->convertPostmanCollectionToKrakendCollection();

            return $convert_docs;
        } catch (Throwable $e) {
            throw new Exception($e->getMessage(), 0, $e);
        }
    }


    private function generateScribeDocumentation() :bool
    {
        // generate new scribe open api doc documentation
        $done = false;
        try {
            $process = Artisan::call('scribe:generate');
            $done = true;
        } catch (Throwable $e) {
            throw new Exception($e->getMessage(), 0, $e);
        }
        return $done;
    }

    private function convertPostmanCollectionToKrakendCollection() :?string
    {
        $message = 'Convert not succesfull';
        $code = 400;
        try{
            $postman_data = $this->readPostmanCollection();
            $main_host_url = $postman_data['variable']['0']['value'];
            $krakend_endpoint_array = array();

            $krakend_counter = 0;
            foreach($postman_data['item'] as $service_key => $service_group_item){
                foreach($service_group_item['item'] as  $endpoint_key => $endpoint_item ) {

                    $krakend_endpoint_array['endpoints'][$krakend_counter]['endpoint'] = $endpoint_item['request']['url']['path'];
                    $krakend_endpoint_array['endpoints'][$krakend_counter]['method']= $endpoint_item['request']['method'];
                    $krakend_endpoint_array['endpoints'][$krakend_counter]['headers_to_pass'] = ["Authorization","Content-Type","Accept"];
                    $krakend_endpoint_array['endpoints'][$krakend_counter]['concurrent_calls'] = 3;
                    $krakend_endpoint_array['endpoints'][$krakend_counter]['output_encoding'] = "json";
                    $krakend_endpoint_array['endpoints'][$krakend_counter]['timeout'] = "4s";
                    $krakend_endpoint_array['endpoints'][$krakend_counter]['backend'][0]['url_pattern'] = $endpoint_item['request']['url']['path'];
                    $krakend_endpoint_array['endpoints'][$krakend_counter]['backend'][0]['method'] = $endpoint_item['request']['method'];
                    $krakend_endpoint_array['endpoints'][$krakend_counter]['backend'][0]['extra_config']['github.com/devopsfaith/krakend/http']['return_error_details'] =  "backend_alias";
                    $krakend_endpoint_array['endpoints'][$krakend_counter]['backend'][0]['host'][] = 'https://'.$main_host_url;
                    $krakend_endpoint_array['endpoints'][$krakend_counter]['backend'][0]['encoding'] = 'json';
                    if ($endpoint_item['request']['method']=='POST' || $endpoint_item['request']['method']=='PUT' ){
                        if (isset($endpoint_item['request']['body']['raw'])) {
                            $body_params = json_decode($endpoint_item['request']['body']['raw'],true);
                            $param_array = $body_params['data']['attributes'];
                            $krakend_endpoint_array['endpoints'][$krakend_counter]['querystring_params'] = array_keys($param_array);
                        }
                    }
                    $krakend_counter++;
                }
            }

            $final_json_file = json_encode($krakend_endpoint_array);
            $final_name = $postman_data['info']['name'];
            file_put_contents('../public/docs/'.$final_name.'.json',$final_json_file);
            $message = 'Convertion is succesfull';
            $code = 200;
        } catch (Throwable $e) {
            $message = 'an error occured, contact the admin';
            $code = 500;
        }
        return response()->json($message,$code);
    }
    private function readPostmanCollection() : array{

        $postman_collection_json_file = file_get_contents('../public/docs/collection.json');
        if ($postman_collection_json_file) {
            $postman_collection_json = json_decode($postman_collection_json_file, true);
            return $postman_collection_json;
        } else{
            throw new Exception("Error occured");
        }
    }

}
