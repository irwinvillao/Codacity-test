<?php

namespace Throne\Entity\Repository;

use Doctrine\ORM\Query;
use Throne\Entity\Review;

require_once realpath(__DIR__ . '/../../../../../THR.php');

class LogSsoRepository extends BaseRepository
{
    public function addLogSso(array $params){
        $em  = $this->getEntityManager();
        $log = new \Throne\Entity\LogSso;
        $this->updateFromArray($log, $params, [
                    'server'    => 'setServer',
                    'httpUserAgent' => 'setHttpUserAgent',
                    'remoteAddr' => 'setRemoteAddr',
                    'httpReferer'   => 'setHttpReferer',
                    'url'           => 'setUrl',
                    'date'          => 'setDate',
                    'brokerId'      => 'setBrokerId',
                    'email'         => 'setEmail',
                    'auth'          => 'setAuth',
                    'domain'        => 'setDomain',
                    'parameter'     => 'setParameter',
                    'status'        => 'setStatus',
                    'type'          => 'setType'
                ], false);

        $em->persist($log);
        $em->flush();

        return $log->getId();
    }
}
