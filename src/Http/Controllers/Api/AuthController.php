<?php

namespace LaravelReady\LicenseServer\Http\Controllers\Api;

use Illuminate\Http\Request;

use LaravelReady\LicenseServer\Models\IpAddress;
use LaravelReady\UltimateSupport\Supports\IpSupport;
use LaravelReady\LicenseServer\Services\LicenseService;
use LaravelReady\LicenseServer\Http\Controllers\BaseController;
use Illuminate\Support\Facades\Config;

class AuthController extends BaseController
{
    /**
     * Login with sanctum
     *
     * @param Request $request
     * @return Response
     */
    public function login(Request $request)
    {
        $request->validate([
            'license_key' => 'required|string|uuid',
        ]);

        $domain = $request->input('ls_domain');
        $licenseKey = $request->input('license_key');

        $license = LicenseService::getLicenseByDomain($domain, $licenseKey);

        if ($license) {
            $license->tokens()->where('name', $domain)->delete();

            // $ipAddress = IpAddress::where('license_id', $license->id)->first();
            $serverIpAddress = IpSupport::getIpAddress();
            $ipAddress = IpAddress::updateOrCreate([
                'license_id' => $license->id,
            ],[
                'ip_address' => $serverIpAddress['ip_address'],
            ]);

            if (Config::get('license-server.authentication.enable_ip_guard') && $ipAddress && $ipAddress->ip_address != $serverIpAddress['ip_address']) {
                return response([
                    'status' => false,
                    'message' => 'This IP address is not allowed. Please contact the license provider.',
                ], 401);
            }

            $licenseAccessToken = $license->createToken($domain, ['license-access']);

            return [
                'status' => true,
                'message' => 'Successfully logged in.',
                'access_token' => explode('|', $licenseAccessToken->plainTextToken)[1],
            ];
        }

        return response([
            'status' => false,
            'message' => 'Invalid license key or lincese source.',
        ], 401);
    }
}
