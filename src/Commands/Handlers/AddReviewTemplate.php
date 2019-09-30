<?php

namespace Matecat\Dqf\Commands\Handlers;

use Matecat\Dqf\Commands\CommandHandler;
use Matecat\Dqf\Constants;
use Teapot\StatusCode;

class AddReviewTemplate extends CommandHandler {
    protected function setRules() {
        $rules = [
                'sessionId'           => [
                        'required' => true,
                        'type'     => Constants::DATA_TYPE_STRING,
                ],
                'projectKey'          => [
                        'required' => true,
                        'type'     => Constants::DATA_TYPE_STRING,
                ],
                'templateName'        => [
                        'required' => true,
                        'type'     => Constants::DATA_TYPE_STRING,
                ],
                'reviewType'          => [
                        'required' => true,
                        'type'     => Constants::DATA_TYPE_STRING,
                        'values'   => 'correction|error_typology|combined',
                ],
                'severityWeights'     => [
                        'required'    => false,
                        'type'        => Constants::DATA_TYPE_STRING,
                        'required_if' => [ 'reviewType', Constants::LOGICAL_OPERATOR_EQUALS, 'combined|error_typology' ]
                ],
                'errorCategoryIds[0]' => [
                        'required'    => false,
                        'type'        => Constants::DATA_TYPE_INTEGER,
                        'required_if' => [ 'reviewType', Constants::LOGICAL_OPERATOR_EQUALS, 'combined|error_typology' ]
                ],
                'errorCategoryIds[1]' => [
                        'required' => false,
                        'type'     => Constants::DATA_TYPE_INTEGER,
                ],
                'errorCategoryIds[2]' => [
                        'required' => false,
                        'type'     => Constants::DATA_TYPE_INTEGER,
                ],
                'passFailThreshold'   => [
                        'required'    => false,
                        'type'        => Constants::DATA_TYPE_DOUBLE,
                        'required_if' => [ 'reviewType', Constants::LOGICAL_OPERATOR_EQUALS, 'combined|error_typology' ]
                ],
                'sampling'            => [
                        'required' => false,
                        'type'     => Constants::DATA_TYPE_DOUBLE,
                ],
                'isPublic'            => [
                        'required' => false,
                        'type'     => Constants::DATA_TYPE_BOOLEAN,
                ],
        ];

        $this->rules = $rules;
    }

    /**
     * @param array $params
     *
     * @return mixed|void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handle( $params = [] ) {
        $response = $this->httpClient->request( Constants::HTTP_VERBS_CREATE, $this->buildUri( 'user/reviewTemplate', [] ), [
                'headers' => [
                        'projectKey' => $params[ 'projectKey' ],
                        'sessionId'  => $params[ 'sessionId' ],
                        'email'      => isset( $params[ 'generic_email' ] ) ? $params[ 'generic_email' ] : null,
                ],
        ] );

        if ( $response->getStatusCode() === StatusCode::CREATED ) {
            return $this->decodeResponse( $response );
        }
    }
}