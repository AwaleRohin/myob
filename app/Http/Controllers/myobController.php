<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Myob;
use App\MyobClient;
use App\MyobInvoice;

class myobController extends Controller
{
    public function get_crf_uri_or_token($request)
    {
        $bearer_token = $request->header('Authorization');
        $split_access_token = explode(' ', $bearer_token, 2);
        $access_token = $split_access_token[1];
        try {
            $token = Myob::where('access_token', $access_token)->first();
        } catch (ModelNotFoundException $exception) {
            return null;
        }
        return $token;
    }


    public function post_req_headers($request)
    {
        $headers = [
            'x-myobapi-key' => env("MYOB_CLIENT_ID"),
            'Authorization' => $request->header("Authorization"),
            'x-myobapi-cftoken' => $request->header('x-myobapi-cftoken'),
            'x-myobapi-version' => $request->header('x-myobapi-version'),
            'Content-Type' => 'application/json'
        ];
        return $headers;
    }


    public function myob_redirect()
    {
        $url = "https://secure.myob.com/oauth2/account/authorize?client_id=znrfbsjqgwk9qatz7stqcecf&redirect_uri=http://localhost:8000/accounts&response_type=code&scope=CompanyFile";
        return redirect()->to($url);
    }

    /**
     * @OA\Get(
     *     path="/",
     *     summary="Redirects to myob login page to get necessary access_token",
     *     tags={"Myob"},
     *     @OA\Response(
     *         response="200",
     *         description="Returns access token, refresh token along with some basic other info",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                  @OA\Property(
     *                     property="access_token",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="token_type",
     *                     type="string"
     *                 ),
     *                  @OA\Property(
     *                     property="expires_in",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="refresh_token",
     *                     type="string"
     *                 ),
     *                  @OA\Property(
     *                     property="scope",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="user",
     *                     type="object", properties={
     *                      @OA\Property(property="uid", type="string"),
     *                      @OA\Property(property="email", type="string")
     *                 }),
     *                 example={"access_token":"A77vnJ5IYQmXxtHLHf1wipwJ-ritfSQB4Yn40X0LBQTFh75vjgQgvGbjmldj4Jqo8fnkEbsh--33ZC90XHtA8GGVMp5KcmMrHJulKU_P7Hv836tyBp_94F24o6Srn5h-1p7WIPPS72i4UtNs57gfDoPeP3RCNa6chMDc7blRwBYtM0ce2DmOgOK","token_type":"bearer","expires_in":"1200","refresh_token":"Ta7J!IAAAABX4_n6xm0ejXjBguhKHLM6LKyDualw1cCPLx0HZbXQvsQAAAAHIozyXQtIK5LdL46Xe84JNIWIHF50wJ0Aqf2Dh4NeeLB2YRwnWXd7WOkOCgh6z6_Vpu9sxmllT5_yZQiW_LKPiakx","scope":"CompanyFile","user":{"uid":"69f9884a7-7rt0-4d58-8439-93euhha4b29d","username":"example@gmail.com"}}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request.",
     *     ),
     * )
     */
    public function access_token(Request $request)
    {
        $code = $request->code;
        $url = "https://secure.myob.com/oauth2/v1/authorize/";
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];
        $client = new \GuzzleHttp\Client([
            'headers' => $headers
        ]);
        $redirect_uri = "http://localhost:8000/accounts";
        try {
            $response = $client->request('POST', $url, [
                'form_params' => [
                    'client_id' => env("MYOB_CLIENT_ID"),
                    'client_secret' => env("MYOB_CLIENT_SECRET"),
                    'grant_type' => env("MYOB_GRANT_TYPE"),
                    'code' => $code,
                    'redirect_uri' => $redirect_uri,
                    'scope' => env("MYOB_SCOPE")
                ]
            ]);
        } catch (\Exception $exception) {
            $response = $exception->getResponse()->getBody(true);
            return response()->json([
                'status' => false,
                'error' => json_decode((string) $response, true)
            ]);
        }
        $body = $response->getBody();
        $result =  json_decode((string) $body, true);
        try {
            $email = Myob::where('email', $result['user']['username'])->first();
        } catch (ModelNotFoundException $exception) {
            $email = null;
        }
        if ($email == null) {
            $myob = new Myob();
            $myob->userid = $result['user']['uid'];
            $myob->access_token = $result['access_token'];
            $myob->email = $result['user']['username'];
            $myob->refresh_token = $result['refresh_token'];
            $myob->save();
        } else {
            $email->access_token = $result['access_token'];
            $email->refresh_token = $result['refresh_token'];
            $email->save();
        }
        return response()->json([
            $result
        ]);
    }


     /**
     * @OA\Get(
     *     path="/accountright",
     *     @OA\Parameter(
     *         name="Authorization",
     *         in="header",
     *         description="bearer token -- access token",
     *         required=true,
     *         @OA\Schema(type="string"),
     *      ),
     *     @OA\Parameter(
     *         name="x-myobapi-key",
     *         in="header",
     *         description="myob client secret",
     *         required=true,
     *         @OA\Schema(type="string"),
     * 
     *     ),
     *     summary="Account Right Live",
     *     tags={"Myob"},
     *     @OA\Response(
     *         response="200",
     *         description="Returns company uid and other info",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                  @OA\Property(
     *                     property="Id",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="Name",
     *                     type="string"
     *                 ),
     *                  @OA\Property(
     *                     property="LibraryPath",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="ProductVersion",
     *                     type="string"
     *                 ),
     *                  @OA\Property(
     *                     property="ProductLevel",
     *                    type="object", properties={
     *                      @OA\Property(property="Code", type="string"),
     *                      @OA\Property(property="Name", type="string")
     *                 }),
     *                 @OA\Property(
     *                     property="CheckOutDate",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="CheckOutBy",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="Uri",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="Country",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="LauncherId",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="SerialNumber",
     *                     type="string"
     *                 ),
     *                 example={
     *                   "Id": "g856461f-b3dg-p05r-b7t2-f2c4f066078e",
     *                   "Name": "API Sandbox Demo 125",
     *                   "LibraryPath": "API Sandbox Demo 125",
     *                   "ProductVersion": "2019.4",
     *                   "ProductLevel": {
     *                      "Code": 30,
     *                      "Name": "Plus"
     *                   },
     *                   "CheckedOutDate": null,
     *                   "CheckedOutBy": null,
     *                   "Uri": "https://ar1.api.myob.com/accountright/g856461f-b3dg-p05r-b7t2-f2c4f066078e",
     *                   "Country": "AU",
     *                   "LauncherId": "5abb5380-1gh7-80k9-8r64-gfe0c63347fa",
     *                   "SerialNumber": "728164104656"
     *              })
     *         )
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request.",
     *     ),
     * )
     */   
    public function accountright_myob(Request $request)
    {
        $headers = [
            'x-myobapi-key' => env("MYOB_CLIENT_ID"),
            'Authorization' => $request->header("Authorization")
        ];
        $url = "https://api.myob.com/accountright";
        $client = new \GuzzleHttp\Client([
            'headers' => $headers
        ]);
        try {
            $response = $client->request('GET', $url);
        } catch (\Exception $exception) {
            $response = $exception->getResponse()->getBody(true);
            return response()->json([
                'status' => false,
                'error' => json_decode((string) $response, true)
            ]);
        }
        $body = $response->getBody();
        $result =  json_decode((string) $body, true);
        try {
            $myob_client = new MyobClient;
            $response = $myob_client->createMyobClient($result);
        } catch (\Exception $exception) {
            return response()->json([
                "status" => false,
                "message" => "Could not create myob client"
            ]);
        };
        return response()->json(
            $response
        );
    }


    /**
     * @OA\Get(
     *     path="/refresh",
     *     @OA\Parameter(
     *         name="Authorization",
     *         in="header",
     *         description="bearer token -- access token",
     *         required=true,
     *         @OA\Schema(type="string"),
     *      ),
     *     @OA\Parameter(
     *         name="x-myobapi-key",
     *         in="header",
     *         description="myob client secret",
     *         required=true,
     *         @OA\Schema(type="string"),
     * 
     *     ),
     *     summary="Refresh access token",
     *     tags={"Myob"},
     *     @OA\Response(
     *         response="200",
     *         description="Returns new access_token and other info",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                  @OA\Property(
     *                     property="access_token",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="token_type",
     *                     type="string"
     *                 ),
     *                  @OA\Property(
     *                     property="expires_in",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="refresh_token",
     *                     type="string"
     *                 ),
     *                  @OA\Property(
     *                     property="scope",
     *                     type="string"
     *                 ),
     *                 example={
     *                      "access_token": "AAEAAI3H59w6F9JPGcp3dXy95pTJK0waL6AwiLdoQ81ncmxeVgbq_4LF9uDy5Gm0c8IjSTAoloZAd5C8rhDXHsTNlYRN29Q7gUtqcPRu3UgaWrEEDTCEaGgoK6Y9xmDyUyMG-G_wB6yctDLThyVxfW",
     *                      "token_type": "bearer",
     *                      "expires_in": "1200",
     *                      "refresh_token": "Ta7J!IAAAABM7-Bybtc3FXSCv5EoLDxVz0occm27cUxZ_LMm-p6_AsQAAAAHudWF_CWSa_eID89iGDVe1Rij-Xgt5a6zFZ2IjbwSOcdh3C0JCYrefm5D7tI3gxkvM8QziRusgfUZTs9wbi-pVY7gQOxW2KEU_XYSp4BNfl18JIXe8R4tUM9TlgOO0fbrMaPPIafA9sdYc_u4Ag",
     *                      "scope": "CompanyFile"
     *              })
     *         )
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request.",
     *     ),
     * )
     */ 
    public function refresh_access_token(Request $request)
    {
        $token = $this->get_crf_uri_or_token($request);
        if ($token == null) {
            return response()->json([
                'status' => false,
                'message' => 'Couldn\'t refresh access token.Something went wrong!!'
            ]);
        }
        $refresh_token = $token->refresh_token;
        $url = "https://secure.myob.com/oauth2/v1/authorize/";
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];
        $client = new \GuzzleHttp\Client([
            'headers' => $headers
        ]);
        $client_id = env("MYOB_CLIENT_ID");
        $client_secret = env("MYOB_CLIENT_SECRET");
        $grant_type = env("MYOB_REFRESH_GRANT");
        try {
            $response = $client->request('POST', $url, [
                'form_params' => [
                    'client_id' => $client_id,
                    'client_secret' => $client_secret,
                    'grant_type' => $grant_type,
                    'refresh_token' => $refresh_token,
                ]
            ]);
        } catch (\Exception $exception) {
            $response = $exception->getResponse()->getBody(true);
            return response()->json([
                'status' => false,
                'error' => json_decode((string) $response, true)
            ]);
        }
        $body = $response->getBody();
        $result =  json_decode((string) $body, true);
        $token->access_token = $result['access_token'];
        $token->refresh_token = $result['refresh_token'];
        $token->save();
        return response()->json([
            $result
        ], 200);
    }


    /**
     * @OA\Post(
     *     path="/sales/invoice/service",
     *     @OA\Parameter(
     *         name="Authorization",
     *         in="header",
     *         description="bearer token -- access token",
     *         required=true,
     *         @OA\Schema(type="string"),
     *      ),
     *     @OA\Parameter(
     *         name="x-myobapi-version",
     *         in="header",
     *         description="account right version",
     *         required=true,
     *         @OA\Schema(type="string"),
     * 
     *     ),
     *     @OA\Parameter(
     *         name="x-myobapi-cftoken",
     *         in="header",
     *         description="base64encoded string of username and password",
     *         required=true,
     *         @OA\Schema(type="string"),
     * 
     *     ),
     *     summary="Create Invoice of Services",
     *     tags={"Myob"},
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="customer_uid",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="account_uid",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="taxcode_uid",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="total_amount",
     *                     type="integer"
     *                 ),
     *                 @OA\Property(
     *                     property="rowversion",
     *                     type="integer"
     *                 ),
     *                 example={
  	 *                      "customer_uid": "8013da04-7cb5-49c5-a8ce-1e25b402aab0",
     *                      "account_uid": "d3f55ef1-ce77-4ef2-a415-61d04db2c5fc",
     *                      "taxcode_uid": "2ab1cf79-bce8-4da0-986c-a3bbdd1d02bc",
     *                      "total_amount": -49.5,
     *                      "rowversion": "-3848888831541510144"
     *              })
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns status and message",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                  @OA\Property(
     *                     property="status",
     *                     type="boolean"
     *                 ),
     *                 @OA\Property(
     *                     property="message",
     *                     type="string"
     *                 ),
     *                 example={
     *                      "status" : true,
     *                      "message" :"Invoice created"
     *              })
     *         )
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request.",
     *     ),
     * )
     */ 
    public function create_service_invoice(Request $request)
    {
        $uri = $this->get_crf_uri_or_token($request);
        if ($uri == null) {
            return response()->json([
                'status' => false,
                'message' => 'crf url not found'
            ]);
        }
        $crf_uri = $uri->crf_uri;
        $url = $crf_uri . "/Sale/Invoice/Service";
        $date = \Carbon\Carbon::now()->toDateTimeString();
        $customer_uid = $request->customer_uid;
        $total = $request->total_amount;
        $account_uid = $request->account_uid;
        $taxcode_uid = $request->taxcode_uid;
        $rowversion = $request->rowversion;
        $data =[
            'account_uid'=>$account_uid,
            'customer_uid'=>$customer_uid
        ];
        $headers = $this->post_req_headers($request);
        $client = new \GuzzleHttp\Client([
            'headers' => $headers
        ]);

        try {
            $response = $client->request('POST', $url, [
                'json' => [
                    'Date' => $date,
                    'Customer' => [
                        'UID' => $customer_uid
                    ],
                    'Lines' => [[
                        'Total' => $total,
                        'Account' => [
                            'UID' => $account_uid
                        ],
                        'TaxCode' => [
                            'UID' => $taxcode_uid
                        ],
                        'RowVersion' => $rowversion
                    ]]
                ]
            ]);
        } catch (\Exception $exception) {
            $response = $exception->getResponse()->getBody(true);
            return response()->json([
                'status' => false,
                'error' => json_decode((string) $response, true)
            ]);
        }
        $invoice = new MyobInvoice;
        $invoice = $invoice->createMyobServiceInvoice($data);

        return response()->json(
            $invoice
        , 201);
    }


    /**
     * @OA\Post(
     *     path="/sales/payments",
     *     @OA\Parameter(
     *         name="Authorization",
     *         in="header",
     *         description="bearer token -- access token",
     *         required=true,
     *         @OA\Schema(type="string"),
     *      ),
     *     @OA\Parameter(
     *         name="x-myobapi-version",
     *         in="header",
     *         description="account right version",
     *         required=true,
     *         @OA\Schema(type="string"),
     * 
     *     ),
     *     @OA\Parameter(
     *         name="x-myobapi-cftoken",
     *         in="header",
     *         description="base64encoded string of username and password",
     *         required=true,
     *         @OA\Schema(type="string"),
     * 
     *     ),
     *     summary="Create Payment/s",
     *     tags={"Myob"},
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="deposit_to",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="account_uid",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="invoice_uid",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="customer_uid",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="amount_applied",
     *                     type="integer"
     *                 ),
     *                 @OA\Property(
     *                     property="type",
     *                     type="string"
     *                 ),
     *                 example={
     *                      "deposit_to":"Account",
	 *                      "account_uid":"d3f55ef1-ce77-4ef2-a415-61d04db2c5fc",
	 *                      "invoice_uid":"bbd73e1b-214b-4543-bd22-0798849ad32f",
	 *                      "customer_uid":"8013da04-7cb5-49c5-a8ce-1e25b402aab0",
	 *                      "amount_applied":496.5,
	 *                      "type":"Invoice"
     *              })
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns status and message",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                  @OA\Property(
     *                     property="status",
     *                     type="boolean"
     *                 ),
     *                 @OA\Property(
     *                     property="message",
     *                     type="string"
     *                 ),
     *                 example={
     *                      "status" : true,
     *                      "message" :"Payment created"
     *              })
     *         )
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request.",
     *     ),
     * )
     */
    public function payment(Request $request)
    {
        $uri = $this->get_crf_uri_or_token($request);
        if ($uri == null) {
            return response()->json([
                'status' => false,
                'message' => 'crf url not found'
            ]);
        }
        $crf_uri = $uri->crf_uri;
        $url = $crf_uri . "/Sale/CustomerPayment";

        $deposit_to = $request->deposit_to;
        $account_uid = $request->account_uid;
        $invoice_uid = $request->invoice_uid;
        $amount_applied = $request->amount_applied;
        $customer_uid = $request->customer_uid;
        $type = $request->type;

        $headers = $this->post_req_headers($request);
        $client = new \GuzzleHttp\Client([
            'headers' => $headers
        ]);
        try {
            $response = $client->request('POST', $url, [
                'json' => [
                    'PayFrom' => $deposit_to,
                    'Account' => [
                        'UID' => $account_uid
                    ],
                    'Customer' => [
                        'UID' => $customer_uid
                    ],
                    'Invoices' => [[
                        'UID' => $invoice_uid,
                        'AmountApplied' => $amount_applied,
                        'Type' => $type
                    ]]
                ]
            ]);
        } catch (\Exception $exception) {
            $response = $exception->getResponse()->getBody(true);
            return response()->json([
                'status' => false,
                'error' => json_decode((string) $response, true)
            ]);
        }
        $body = $response->getBody();
        $result =  json_decode((string) $body, true);
        return response()->json([
            'status' => true,
            'message' => 'Payment created'
        ], 201);
    }



    /**
     * @OA\Post(
     *     path="/sales/payments-with-discount",
     *     @OA\Parameter(
     *         name="Authorization",
     *         in="header",
     *         description="bearer token -- access token",
     *         required=true,
     *         @OA\Schema(type="string"),
     *      ),
     *     @OA\Parameter(
     *         name="x-myobapi-version",
     *         in="header",
     *         description="account right version",
     *         required=true,
     *         @OA\Schema(type="string"),
     * 
     *     ),
     *     @OA\Parameter(
     *         name="x-myobapi-cftoken",
     *         in="header",
     *         description="base64encoded string of username and password",
     *         required=true,
     *         @OA\Schema(type="string"),
     * 
     *     ),
     *     summary="Create Payment/s with discount",
     *     tags={"Myob"},
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="deposit_to",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="account_uid",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="invoice_uid",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="customer_uid",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="amount_applied",
     *                     type="integer"
     *                 ),
     *                 @OA\Property(
     *                     property="discount_applied",
     *                     type="integer"
     *                 ),
     *                 @OA\Property(
     *                     property="type",
     *                     type="string"
     *                 ),
     *                 example={
     *                      "deposit_to":"Account",
	 *                      "account_uid":"d3f55ef1-ce77-4ef2-a415-61d04db2c5fc",
	 *                      "invoice_uid":"bbd73e1b-214b-4543-bd22-0798849ad32f",
	 *                      "customer_uid":"8013da04-7cb5-49c5-a8ce-1e25b402aab0",
	 *                      "amount_applied":496.5,
	 *                      "discount_applied":26,
	 *                      "type":"Invoice"
     *              })
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns status and message",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                  @OA\Property(
     *                     property="status",
     *                     type="boolean"
     *                 ),
     *                 @OA\Property(
     *                     property="message",
     *                     type="string"
     *                 ),
     *                 example={
     *                      "status" : true,
     *                      "message" :"Payment with discount created"
     *              })
     *         )
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request.",
     *     ),
     * )
     */
    public function payemtWithDiscount(Request $request)
    {
        $uri = $this->get_crf_uri_or_token($request);
        if ($uri == null) {
            return response()->json([
                'status' => false,
                'message' => 'crf url not found'
            ]);
        }
        $crf_uri = $uri->crf_uri;
        $url = $crf_uri . "/Sale/CustomerPayment/RecordWithDiscountsAndFees";

        $deposit_to = $request->deposit_to;
        $account_uid = $request->account_uid;
        $invoice_uid = $request->invoice_uid;
        $amount_applied = $request->amount_applied;
        $discount_applied = $request->discount_applied;
        $customer_uid = $request->customer_uid;
        $type = $request->type;
        $headers = $this->post_req_headers($request);
        $client = new \GuzzleHttp\Client([
            'headers' => $headers
        ]);
        try {
            $response = $client->request('POST', $url, [
                'json' => [
                    'PayFrom' => $deposit_to,
                    'Account' => [
                        'UID' => $account_uid
                    ],
                    'Customer' => [
                        'UID' => $customer_uid
                    ],
                    'Lines' => [[
                        'Sale' => [
                            'UID' => $invoice_uid
                        ],
                        'AmountApplied' => $amount_applied,
                        'DiscountApplied' => $discount_applied,
                        'Type' => $type
                    ]]
                ]
            ]);
        } catch (\Exception $exception) {
            $response = $exception->getResponse()->getBody(true);
            return response()->json([
                'status' => false,
                'error' => json_decode((string) $response, true)
            ]);
        }
        $body = $response->getBody();
        $result =  json_decode((string) $body, true);
        return response()->json([
            'status' => true,
            'message' => 'Payment with discount created'
        ], 201);
    }



    /**
     * @OA\Post(
     *     path="sales/invoice/item",
     *     @OA\Parameter(
     *         name="Authorization",
     *         in="header",
     *         description="bearer token -- access token",
     *         required=true,
     *         @OA\Schema(type="string"),
     *      ),
     *     @OA\Parameter(
     *         name="x-myobapi-version",
     *         in="header",
     *         description="account right version",
     *         required=true,
     *         @OA\Schema(type="string"),
     * 
     *     ),
     *     @OA\Parameter(
     *         name="x-myobapi-cftoken",
     *         in="header",
     *         description="base64encoded string of username and password",
     *         required=true,
     *         @OA\Schema(type="string"),
     * 
     *     ),
     *     summary="Create item invoice",
     *     tags={"Myob"},
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="customer_uid",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="item_uid",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="taxcode_uid",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="ship_quantity",
     *                     type="integer"
     *                 ),
     *                 @OA\Property(
     *                     property="total_amount",
     *                     type="integer"
     *                 ),
     *                 @OA\Property(
     *                     property="rowversion",
     *                     type="integer"
     *                 ),
     *                 example={
     *                      "customer_uid": "8013da04-7cb5-49c5-a8ce-1e25b402aab0",
	 *                      "item_uid": "4866c877-5b65-4567-900e-310a8a62897f",
	 *                      "taxcode_uid": "2ab1cf79-bce8-4da0-986c-a3bbdd1d02bc",
	 *                      "ship_quantity": 20,
	 *                      "total_amount":399.50,
	 *                      "rowversion": "-3848888831541510144"
     *              })
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns status and message",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                  @OA\Property(
     *                     property="status",
     *                     type="boolean"
     *                 ),
     *                 @OA\Property(
     *                     property="message",
     *                     type="string"
     *                 ),
     *                 example={
     *                      "status" : true,
     *                      "message" :"Invoice created"
     *              })
     *         )
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request.",
     *     ),
     * )
     */
    public function create_item_invoice(Request $request){
        $uri = $this->get_crf_uri_or_token($request);
        if ($uri == null) {
            return response()->json([
                'status' => false,
                'message' => 'crf url not found'
            ]);
        }
        $crf_uri = $uri->crf_uri;
        $url = $crf_uri . "/Sale/Invoice/Item";
        $date = \Carbon\Carbon::now()->toDateTimeString();
        $customer_uid = $request->customer_uid;
        $ship_quantity = $request->ship_quantity;
        $total = $request->total_amount;
        $item_uid = $request->item_uid;
        $taxcode_uid = $request->taxcode_uid;
        $rowversion = $request->rowversion;
        $data =[
            'customer_uid'=>$customer_uid,
            'item_uid'=>$item_uid
        ];

        $headers = $this->post_req_headers($request);
        $client = new \GuzzleHttp\Client([
            'headers' => $headers
        ]);

        try {
            $response = $client->request('POST', $url, [
                'json' => [
                    'Date' => $date,
                    'Customer' => [
                        'UID' => $customer_uid
                    ],
                    'Lines' => [[
                        'ShipQuantity'=>$ship_quantity,
                        'Total' => $total,
                        'Item' => [
                            'UID' => $item_uid
                        ],
                        'TaxCode' => [
                            'UID' => $taxcode_uid
                        ],
                        'RowVersion' => $rowversion
                    ]]
                ]
            ]);
        } catch (\Exception $exception) {
            $response = $exception->getResponse()->getBody(true);
            return response()->json([
                'status' => false,
                'error' => json_decode((string) $response, true)
            ]);
        }
        $invoice = new MyobInvoice;
        $invoice = $invoice->createMyobItemInvoice($data); 
        return response()->json(
            $invoice
        , 201);
    }




    public function get_services_invoices(Request $request){
        $uri = $this->get_crf_uri_or_token($request);
        if ($uri == null) {
            return response()->json([
                'status' => false,
                'message' => 'crf url not found'
            ]);
        }
        $crf_uri = $uri->crf_uri;
        $url = $crf_uri . "/Sale/Invoice/Service";

        $headers = $this->post_req_headers($request);
        $client = new \GuzzleHttp\Client([
            'headers' => $headers
        ]);

        try {
            $response = $client->request('GET', $url);
        } catch (\Exception $exception) {
            $response = $exception->getResponse()->getBody(true);
            return response()->json([
                'status' => false,
                'error' => json_decode((string) $response, true)
            ]);
        }
        $body = $response->getBody();
        $result =  json_decode((string) $body, true);
        return response()->json([
            $result
        ], 200);
    }


    public function get_items_invoices(Request $request){
        $uri = $this->get_crf_uri_or_token($request);
        if ($uri == null) {
            return response()->json([
                'status' => false,
                'message' => 'crf url not found'
            ]);
        }
        $crf_uri = $uri->crf_uri;
        $url = $crf_uri . "/Sale/Invoice/Item";

        $headers = $this->post_req_headers($request);
        $client = new \GuzzleHttp\Client([
            'headers' => $headers
        ]);

        try {
            $response = $client->request('GET', $url);
        } catch (\Exception $exception) {
            $response = $exception->getResponse()->getBody(true);
            return response()->json([
                'status' => false,
                'error' => json_decode((string) $response, true)
            ]);
        }
        $body = $response->getBody();
        $result =  json_decode((string) $body, true);
        return response()->json([
            $result
        ], 200);
    }


    public function get_payments(Request $request){
        $uri = $this->get_crf_uri_or_token($request);
        if ($uri == null) {
            return response()->json([
                'status' => false,
                'message' => 'crf url not found'
            ]);
        }
        $crf_uri = $uri->crf_uri;
        $url = $crf_uri . "/Sale/CustomerPayment";

        $headers = $this->post_req_headers($request);
        $client = new \GuzzleHttp\Client([
            'headers' => $headers
        ]);

        try {
            $response = $client->request('GET', $url);
        } catch (\Exception $exception) {
            $response = $exception->getResponse()->getBody(true);
            return response()->json([
                'status' => false,
                'error' => json_decode((string) $response, true)
            ]);
        }
        $body = $response->getBody();
        $result =  json_decode((string) $body, true);
        return response()->json([
            $result
        ], 200);
    }


    public function creditSettlement(Request $request)
    {
        $uri = $this->get_crf_uri_or_token($request);
        if ($uri == null) {
            return response()->json([
                'status' => false,
                'message' => 'crf url not found'
            ]);
        }
        $crf_uri = $uri->crf_uri;
        $url = $crf_uri . "/Sale/CreditSettlement";

        $invoice_uid = $request->invoice_uid;
        $customer_uid = $request->customer_uid;
        $type = $request->type;
        $sale_uid = $request->sale_uid;
        $amount_applied = $request->amount_applied;
        $date = \Carbon\Carbon::now()->toDateTimeString();
        $headers = $this->post_req_headers($request);
        $client = new \GuzzleHttp\Client([
            'headers' => $headers
        ]);
        try {
            $response = $client->request('POST', $url, [
                'json' => [
                    'CreditFromInvoice' => [
                        'UID' => $invoice_uid,
                    ],
                    'Customer' => [
                        'UID' => $customer_uid
                    ],
                    'Date' => $date,
                    'Lines' => [[
                        'Sale' => [
                            'UID' => $invoice_uid
                        ],
                        'AmountApplied' => $amount_applied,
                        'Type' => $type
                    ]]
                ]
            ]);
        } catch (\Exception $exception) {
            $response = $exception->getResponse()->getBody(true);
            return response()->json([
                'status' => false,
                'error' => json_decode((string) $response, true)
            ]);
        }
        $body = $response->getBody();
        $result =  json_decode((string) $body, true);
        return response()->json([
            'status' => true,
            'message' => 'Payment with discount created'
        ], 201);
    }


    public function get_credit_settlements(Request $request){
        $uri = $this->get_crf_uri_or_token($request);
        if ($uri == null) {
            return response()->json([
                'status' => false,
                'message' => 'crf url not found'
            ]);
        }
        $crf_uri = $uri->crf_uri;
        $url = $crf_uri . "/Sale/CreditSettlement";

        $headers = $this->post_req_headers($request);
        $client = new \GuzzleHttp\Client([
            'headers' => $headers
        ]);

        try {
            $response = $client->request('GET', $url);
        } catch (\Exception $exception) {
            $response = $exception->getResponse()->getBody(true);
            return response()->json([
                'status' => false,
                'error' => json_decode((string) $response, true)
            ]);
        }
        $body = $response->getBody();
        $result =  json_decode((string) $body, true);
        return response()->json([
            $result
        ], 200);
    }
}
