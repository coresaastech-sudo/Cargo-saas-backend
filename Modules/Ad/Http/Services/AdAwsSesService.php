<?php

namespace Modules\Ad\Http\Services;

use Aws\SesV2\SesV2Client;
use Aws\Exception\AwsException;
use Modules\Ad\Entities\AdEmailBlacklist;

class AdAwsSesService
{
    protected $client;

    public function __construct()
    {
    }

    /**
     * Get list of suppressed destinations
     */
    public function getSuppressedDestinations()
    {
        
        $this->client = new SesV2Client([
            'version' => 'latest',
            'region'  => config('services.ses.region', env('AWS_DEFAULT_REGION')),
            'credentials' => [
                'key'    => config('services.ses.key'),
                'secret' => config('services.ses.secret'),
            ],
        ]);

        try {
            $result = $this->client->listSuppressedDestinations([]);

            return $result->toArray();
        } catch (AwsException $e) {
            return ['error' => $e->getAwsErrorMessage()];
        }
    }

    /**
     * Remove an email from suppression list
     */
    public function removeFromSuppressionList($email)
    {
        $this->client = new SesV2Client([
            'version' => 'latest',
            'region'  => config('services.ses.region', env('AWS_DEFAULT_REGION')),
            'credentials' => [
                'key'    => config('services.ses.key'),
                'secret' => config('services.ses.secret'),
            ],
        ]);
        try {
            $result = $this->client->deleteSuppressedDestination([
                'EmailAddress' => $email,
            ]);

            return ['success' => true, 'message' => 'Email removed from suppression list'];
        } catch (AwsException $e) {
            return ['success' => false, 'error' => $e->getAwsErrorMessage()];
        }
    }

    /**
     * Add an email to suppression list
     */
    public function addToSuppressionList($email, $reason = 'BOUNCE')
    {
        $this->client = new SesV2Client([
            'version' => 'latest',
            'region'  => config('services.ses.region', env('AWS_DEFAULT_REGION')),
            'credentials' => [
                'key'    => config('services.ses.key'),
                'secret' => config('services.ses.secret'),
            ],
        ]);
        try {
            $result = $this->client->putSuppressedDestination([
                'EmailAddress' => $email,
                'Reason'       => $reason, // BOUNCE or COMPLAINT
            ]);

            return ['success' => true, 'message' => 'Email added to suppression list'];
        } catch (AwsException $e) {
            return ['success' => false, 'error' => $e->getAwsErrorMessage()];
        }
    }

    /**
     * Check an email is exist from suppression list
     */
    public function checkEmail($email)
    {
        $exists = AdEmailBlacklist::where('emailaddress', $email)
            ->where('statusid', 1)
            ->exists();
        if($exists) {
            return true;
        }
        
        if(str_contains($email, '@test.mn') ) {
            return true;
        }

        return false;
    }
}
