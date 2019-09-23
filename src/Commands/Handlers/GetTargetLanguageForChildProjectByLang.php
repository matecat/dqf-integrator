<?php

namespace Matecat\Dqf\Commands\Handlers;

use Matecat\Dqf\Commands\CommandHandler;
use Teapot\StatusCode;

class GetTargetLanguageForChildProjectByLang extends CommandHandler {

    protected $required = [
            'sessionId',
            'projectKey',
            'projectId',
            'fileId',
            'targetLangCode',
    ];

    /**
     * @param array $params
     *
     * @return mixed|void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handle( $params = [] ) {

        $response = $this->httpClient->request( 'GET', $this->buildUri( 'project/child/{projectId}/file/{fileId}/targetLang/{targetLangCode}', [
                        'projectId'      => $params[ 'projectId' ],
                        'fileId'         => $params[ 'fileId' ],
                        'targetLangCode' => $params[ 'targetLangCode' ],
                ]
        ), [
                'headers' => [
                        'projectKey' => $params[ 'projectKey' ],
                        'sessionId'  => $params[ 'sessionId' ],
                ],
        ] );

        if ( $response->getStatusCode() === StatusCode::CREATED ) {
            return $this->decodeResponse( $response );
        }
    }
}
