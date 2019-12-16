<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Myob;

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
            $bearer_token = $request->header('Authorization');
            $split_access_token = explode(' ', $bearer_token, 2);
            $access_token = $split_access_token[1];
            $user = Myob::where('access_token', $access_token)->first();
            $user->crf_uri = $result[0]['Uri'];
            $user->save();
        } catch (ModelNotFoundException $exception) {
            return response()->json([
                "status" => false,
                "message" => "User not found"
            ]);
        };
        return response()->json(
            $result
        );
    }


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


    public function create_invoice(Request $request)
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
        $body = $response->getBody();
        $result =  json_decode((string) $body, true);
        return response()->json([
            'status' => true,
            'message' => 'Invoice created'
        ], 201);
    }


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
}
