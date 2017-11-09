<?php

namespace Throne\Entity\Repository;

use Doctrine\ORM\Query;
use Throne\Entity\CompanyMapper;

class CompanyMapperRepository extends BaseRepository
{
    
    /**
     * Add description
     * ...
     *
     * @param array $parameters
     * @return array
     */
    public function create($parameters){
        
        $companyHasSsoMapper = self::read($parameters["companyId"]);
        $update              = false;
        $result["error"]     = "";
        if (!isset($companyHasSsoMapper["id"])) { //create
            $ssoCompanyNameExists = $this->readBySsoCompanyName($parameters["ssoCompanyName"]);
            if (!$ssoCompanyNameExists){
                $em            = $this->getEntityManager();
                $companyMapper = new CompanyMapper();
                $save          = self::save($companyMapper, $parameters, $update);
                $result        = array_merge($result, $save);
                
            }else{
                $result["error"] = "sso_company_already_exists"; //Sso Company Name already exists
            }
        } else {
            $result["error"] = "has_one_company_mapper"; //Company already has one companymapper
        }
            
        return $result;
    }

    /**
     * Add description
     * ...
     *
     * @param array $parameters
     * @return array
     */
    public function update($parameters){
        
        $companyHasSsoMapper = self::read($parameters["companyId"]);
        $update              = true;
        $result["error"]     = "";
        if (isset($companyHasSsoMapper["id"])) { //update
            $ssoCompanyNameExists = self::readBySsoCompanyName($parameters["ssoCompanyName"], $parameters["companyId"]);
            $em                   = $this->getEntityManager();
            
            if (!isset($ssoCompanyNameExists["id"])) { //update

                $companyMapper = $em->getRepository('Throne\Entity\CompanyMapper')->find($companyHasSsoMapper["id"]);
                $save          = self::save($companyMapper, $parameters, $update);
                $result        = array_merge($result, $save);

            } else {
                $result["error"] = "has_sso_company_name"; //other company has this sso company name
                
            }
        } else {
             $result["error"] = "create_company_mapper"; //you need to create sso company mapper
        }

        return $result;
    }    

    /**
     * Add description
     * ...
     *
     * @param object $companyMapper
     * @param array $parameters
     * @param bool $update
     * @return array
     */
    private function save($companyMapper, $parameters, $update){

        $em = $this->getEntityManager();
        $this->updateFromArray($companyMapper, $parameters, $this->getMethodsCompanyMapper($update), false);
        $em->persist($companyMapper);
        $em->flush();
        
        $result["companyMapper"] = [
            'id'             => $companyMapper->getId(),
            'ssoCompanyName' => $companyMapper->getSsoCompanyName(),
            'companyId'      => $companyMapper->getCompanyId(),
            'ssoMapper'      => $companyMapper->getSsoMapper()
        ];

        return $result;
    }

    /**
     * Add description
     * ...
     *
     * @param integer $companyId
     * @return array
     */
    public function read($companyId){

        $whereOrderBy = $this->buildWhereOrderBy([
            "conditions" => ["a.companyId = :companyId"]
        ]);

        $dql = "
            SELECT a
            FROM Throne\Entity\CompanyMapper a
            WHERE $whereOrderBy
        ";

        $query = $this->getEntityManager()
            ->createQuery($dql)
            ->setHint(Query::HINT_INCLUDE_META_COLUMNS, true)
            ->setParameter('companyId',$companyId)
        ;

        return (array) @$query->getArrayResult()[0];
    }

    /**
     * Add description
     * ...
     *
     * @param string $ssoCompanyName
     * @param integer $companyId
     * @return array
     */
    public function readBySsoCompanyName($ssoCompanyName, $companyId = NULL){

        $whereOrderBy = $this->buildWhereOrderBy([
            "conditions" => ["a.ssoCompanyName = :ssoCompanyName"]
        ]);

        $companyClause = ($companyId > 0) ? " AND a.companyId !=:companyId " : "";

        $dql = "
            SELECT a
            FROM Throne\Entity\CompanyMapper a
            WHERE $whereOrderBy
            $companyClause
        ";
        $query = $this->getEntityManager()
            ->createQuery($dql)
            ->setHint(Query::HINT_INCLUDE_META_COLUMNS, true)
            ->setParameter('ssoCompanyName', $ssoCompanyName)
        ;
        if($companyClause) $query->setParameter('companyId', $companyId);

        return (array) @$query->getArrayResult()[0];
    }

    /**
     * Add description
     * ...
     *
     * @param boolean $update
     * @return array
     */    
    private function getMethodsCompanyMapper($update){
        $methods = [
            'companyId'      => 'setCompanyId',
            'ssoCompanyName' => 'setSsoCompanyName',
            'ssoMapper'      => 'setSsoMapper',
            'created'        => 'setCreated',
            'updated'        => 'setUpdated',
            'updatedBy'      => 'setUpdatedBy',
            'status'         => 'setStatus'
        ];
        if($update) unset($methods["created"]);
        
        return $methods;            
    }

}