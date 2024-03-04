<?php

namespace app\controller;

use app\BaseController;

use AlibabaCloud\SDK\Alidns\V20150109\Alidns;
use \Exception;
use AlibabaCloud\Tea\Exception\TeaError;
use AlibabaCloud\Tea\Utils\Utils;

use Darabonba\OpenApi\Models\Config;
use AlibabaCloud\SDK\Alidns\V20150109\Models\DescribeDomainRecordsRequest;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;
use AlibabaCloud\SDK\Alidns\V20150109\Models\UpdateDomainRecordRequest;
use think\facade\Log;


class Update extends BaseController
{   
    public function index()
    {
        // 获取参数
        $hostname = input("hostname");
        $ip = input("ip");
        $accessKeyId = input("accessKeyId");
        $accessKeySecret = input("accessKeySecret");
        // 验证参数
        if($hostname && $ip && $accessKeyId &&  $accessKeySecret) {            
            $parts = explode('.', $hostname);
            $RR = $parts[0];
            $domainName = $parts[1].'.'.$parts[2];
            // 获取RecordId
            $client = self::createClient($accessKeyId, $accessKeySecret);
            $resDescribeDomainRecordsWithOptions = $this->describeDomainRecords( $client, $RR, $domainName );
            if($resDescribeDomainRecordsWithOptions["code"] === 200) {
                $resUpdateDomainRecordWithOptions = $this->updateDomainRecord($client, $ip, $resDescribeDomainRecordsWithOptions['data']['recordId'], $RR);
                 return json($resUpdateDomainRecordWithOptions)->code($resUpdateDomainRecordWithOptions["code"]);

            }else{
               return json($resDescribeDomainRecordsWithOptions)->code($resDescribeDomainRecordsWithOptions["code"]);
            }
        }else{
           $data = [
                'code' => 404,
                'message' => "Request failed with incomplete parameters",
            ];
                
            return json($data)->code(404);
        }

        // 日志记录
        Log::write( input("hostname").",".input("ip"));
        
    }


    public function describeDomainRecords($client, $RR, $domainName ) {
        try {
            // 复制代码运行请自行打印 API 的返回值
            $describeDomainRecordsRequest = new DescribeDomainRecordsRequest([
                "domainName" => $domainName,
                "keyWord" => $RR
            ]);
            $runtime = new RuntimeOptions([]);
            $res = $client->describeDomainRecordsWithOptions($describeDomainRecordsRequest, $runtime);
            $data = [
                'code' => 200,
                'message' => "success",
                'data' =>  [
                    "recordId" => $res->body->domainRecords->record[0]->recordId
                ]
            ];
                
            return $data;
        }
        catch (Exception $error) {
            if (!($error instanceof TeaError)) {
                $error = new TeaError([], $error->getMessage(), $error->getCode(), $error);
            }
            // // 错误 message
            // var_dump($error->message);
            $errorMessage =  Utils::assertAsString($error->message);
            Log::write( $errorMessage, "error");
            
            $data = [
                'code' => 404,
                'message' => $errorMessage,
            ];
                
            return $data;
        }
    }

    public function updateDomainRecord($client, $ip, $recordId, $RR) {
        try {
            $updateDomainRecordRequest = new UpdateDomainRecordRequest([
                "value" => $ip,
                "recordId" => $recordId,
                "type" => "A",
                "RR" => $RR,
            ]);
            $runtime = new RuntimeOptions([]);
            $res =  $client->updateDomainRecordWithOptions($updateDomainRecordRequest, $runtime);
            $data = [
                'code' => $res->statusCode,
                'message' => "success",
                'data' => $res->body
            ]; 
     
            return $data;
        }
        catch (Exception $error) {
            if (!($error instanceof TeaError)) {
                $error = new TeaError([], $error->getMessage(), $error->getCode(), $error);
            }
        
            // // 错误 message
            // var_dump($error->message);
            $errorMessage =  Utils::assertAsString($error->message);
            Log::write( $errorMessage, "error");
            $data = [
                'code' => 404,
                'message' => $errorMessage,
            ];
            // var_dump($errorMessage);
            if (strpos($errorMessage, "The DNS record already exists") !== false) {
                $data['code'] = 200; 
            }
            
            return $data;
        }
    }

        /**
     * 使用AK&SK初始化账号Client
     * @param string $accessKeyId
     * @param string $accessKeySecret
     * @return Alidns Client
     */
    public static function createClient($accessKeyId, $accessKeySecret){
        $config = new Config([
            // 必填，您的 AccessKey ID
            "accessKeyId" => $accessKeyId,
            // 必填，您的 AccessKey Secret
            "accessKeySecret" => $accessKeySecret
        ]);
        // Endpoint 请参考 https://api.aliyun.com/product/Alidns
        $config->endpoint = "alidns.cn-hangzhou.aliyuncs.com";
        return new Alidns($config);
    }

}
