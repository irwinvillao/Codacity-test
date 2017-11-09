<?php

namespace Throne\Entity\Repository;

use Doctrine\ORM\Query;
use Throne\Entity\SetPasswordRequest as ThroneResetRequest;
use Throne\Entity\User;
use THR\Exception\API_NotFound_Exception;

require_once realpath(__DIR__ . '/../../../../../THR.php');


class UserRepository extends BaseRepository
{
    protected $filterFieldsAllowed = [
        'active'    => 'u.is_active',
        'companies' => 'u.company_id'
    ];

    protected $searchFieldsAllowed = [
        'u.email',
        'u.first_name',
        'u.last_name'
    ];

    protected $sortFieldsAllowed = [
        'u.first_name',
        'u.last_name',
        'u.email',
        'c.display_name',
        'u.active'
    ];

    protected $sortFieldDefault = 'u.first_name';

    public function read($id)
    {
        $clientType = \THR::getClientType();

        $whereOrderBy = $this->buildWhereOrderBy([
            'conditions' => ['u.id = :id'],
        ]);

        $dql = "
            SELECT u, c, r, cr, p
            FROM Throne\Entity\User u
            JOIN u.company c
            LEFT JOIN c.roles cr
            LEFT JOIN u.role r
            LEFT JOIN r.permissions p
            WHERE $whereOrderBy
        ";

        $query = $this->getEntityManager()
            ->createQuery($dql)
            ->setHint(Query::HINT_INCLUDE_META_COLUMNS, true)
            ->setParameter('id', $id)
        ;

        $result = $query->getArrayResult();

        if (!$result) {
            throw new API_NotFound_Exception(sprintf($this->_CI->lang->line('resource_not_found'), 'user', $id));
        }

        $keys = array();
        if( is_array($result[0]['role']['permissions']) && sizeof($result[0]['role']['permissions']) > 0 ){
            $keys = $this->buildAclPermissions($result[0]['role']['permissions']);
        }

        // allow all to access
        $keys[] = 'myaccount';
        $keys[] = 'myaccount.edit';

        if ($clientType === 'thinkhr') {
            $keys[] = 'portal';
            $keys[] = 'training';
            $keys[] = 'system.companies.import';
            $keys[] = 'system.companies.setup';
            $keys[] = 'system.users.import';
            $keys[] = 'system.skus.all';
        }

        // if broker id = paychex / paychex sales : remove system
        if(
            $result[0]['company']['Broker'] == 187624 || 
            $result[0]['company']['Broker'] == 173477 || 
            $result[0]['company']['Broker'] == 205111
        ){

            if(($index = array_search('system.roles.all', $keys)) !== false) {
                unset($keys[$index]);
            }

            if(($index = array_search('system.branding.all', $keys)) !== false) {
                unset($keys[$index]);
            }

            if(($index = array_search('system.configuration.all', $keys)) !== false) {
                unset($keys[$index]);
            }

            if(($index = array_search('system.users.all', $keys)) !== false) {
                unset($keys[$index]);
            }

            if(($index = array_search('system.companies.all', $keys)) !== false) {
                unset($keys[$index]);
            }

            if(($index = array_search('system', $keys)) !== false) {
                unset($keys[$index]);
            }
            $keys = array_values($keys);

        }

        $result[0]['role']['permissionsList'] = $keys;

        unset(
            $result[0]['role']['permissions'],
            $result[0]['password'],
            $result[0]['company']['roles'][0]['permissions']
        );
        return (array) @$result[0];
    }

    public function readAll(array $filters, $search, $order, $limit, $offset)
    {
        $whereOrderBy = $this->buildWhereOrderBy([
            'search'     => $search,
            'filters'    => $filters,
            'conditions' => ['c.is_active = 1'],
            'order'      => $order,
        ]);

        $dql = "
            SELECT c, u, r
            FROM Throne\Entity\User u
            JOIN u.company c
            LEFT JOIN u.role r
            WHERE $whereOrderBy
        ";

        $query = $this->getEntityManager()
            ->createQuery($dql)
            ->setHint(Query::HINT_INCLUDE_META_COLUMNS, true)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->setParameters([])
        ;

        $results = $query->getArrayResult();

        foreach ($results as $key => &$result) {
            if (isset($result['roles'][0])){
                $result['role']['id']           = $result['roles'][0]['id'];
                $result['role']['name']         = $result['roles'][0]['name'];
                $result['role']['description']  = $result['roles'][0]['description'];
                unset($result['roles']);
            }

            // $result['company_display_name']     = $result['company']['display_name'];
            $result['status']                   = $result['active'];
        }

        return $results;
    }

    public function readThroneUserByUsername($username)
    {
        $dql = "
            SELECT u
            FROM Throne\Entity\User u
            JOIN u.company c
            WHERE u.username = :username
            AND u.role IS NOT NULL
            AND c.is_active = 1
            AND c.client_status = 1
        ";

        $query = $this->getEntityManager()
            ->createQuery($dql)
            ->setHint(Query::HINT_INCLUDE_META_COLUMNS, true)
            ->setParameter('username', $username)
        ;

        $result = $query->getArrayResult();

        return (array) @$result[0];
    }

    public function update($id, array $data)
    {
        $user = $this->getEntityManager()->getRepository('Throne\Entity\User')->findOneBy([
            'id'=>$id
        ]);
        if (!$user) {
            throw new API_NotFound_Exception(sprintf($this->_CI->lang->line('resource_not_found'), 'user', $id));
        }

        // Update the user's role
        if ($data['role']) {

            $role = $this->getEntityManager()->getRepository('Throne\Entity\Role')->findOneBy([
                'company' => $user->getCompany()->getId(),
                'id'      => @$data['role']['id'],
            ]);
            if (!$role) {
                throw new \THR_API_Exception(\THR::buildExceptionMessage("Role not found: ".$data['role']['id'], __FILE__, __LINE__));
            }

            $user->setRole($role);
        }

        $user->setModified(time());

        $user = $this->updateFromArray($user, $data, [
            'first_name' => 'setFirstName',
            'last_name'  => 'setLastName',
            'email'      => 'setEmail',
            'username'   => 'setUsername', // Duplicate check needed for username?
            'active'     => 'setActive'
        ], true);

        return $this->read($user->getId());
    }

    /**
     * TODO: Move this out of repository. Repository functions should not know anthing about $_SESSION!
     */
    public function setAuthenticatedUserSessionData($username, $throneUser, $portalUser, $learnUser, $app)
    {
        $clientType    = null;
        $clientId      = 0;
        $brokerId      = 0;

        if ($throneUser) {
            $clientId  = (int) $throneUser->getCompany()->getId();
            $company    = $throneUser->getCompany();

            if (!$company) {
                exit('company not found'); // TODO: handle this
            }

            $brokerId = (int) $company->getBroker()->getId();

            if (!$brokerId) {
                exit('broker_id not found'); // TODO: handle this
            }

            // client type
            if ($clientId == 8148) {
                $clientType = \THR::CLIENT_TYPE_THINKHR;
            } elseif ($clientId == $brokerId) {
                $clientType = \THR::CLIENT_TYPE_BROKER;
            } else {
                $clientType = \THR::CLIENT_TYPE_RE;
            }
        }

        // login/logout url
        // TODO: many of these urls are the same - they won't be when we are done
        switch ($app) {
            case \THR::APP_THRONE:
                $loginRedirectUrl      = 'https://apps.' . \THR::getRootDomain() . '/en-us';
                $logoutRedirectUrl     = 'https://apps.' . \THR::getRootDomain() . '/login';
                $logoutUrl             = 'https://apps.' . \THR::getRootDomain() . '/logout';
                break;

            case \THR::APP_PULSE:
                $loginRedirectUrl      = 'https://apps.' . \THR::getRootDomain() . '/pulse';
                $logoutRedirectUrl     = 'https://apps.' . \THR::getRootDomain() . '/login';
                $logoutUrl             = 'https://apps.' . \THR::getRootDomain() . '/logout';
                break;

            case \THR::APP_LEARN:
                $loginRedirectUrl      = 'https://learn.' . \THR::getRootDomain() . '/training';
                $logoutRedirectUrl     = 'https://apps.'  . \THR::getRootDomain() . '/login';
                $logoutUrl             = 'https://apps.'  . \THR::getRootDomain() . '/logout';
                break;

            default:
                $loginRedirectUrl      = null;
                $logoutRedirectUrl     = 'https://apps.' . \THR::getRootDomain() . '/login';
                $logoutUrl             = 'https://apps.' . \THR::getRootDomain() . '/logout';
        }

        $_SESSION['THR_AUTH_USER'] = [
            'client_type'           => $clientType,
            'client_id'             => $clientId,
            'broker_id'             => $brokerId,
            'thrcontactid'          => $throneUser ? $throneUser->getId() : $learnUser->getThrcontactid(),
            'portal_id'             => $portalUser ? $portalUser->getId() : null,
            'learn_id'              => $learnUser  ? $learnUser->getId()  : null,
            'username'              => $username,
            'login_redirect_url'    => $loginRedirectUrl,
            'logout_redirect_url'   => $logoutRedirectUrl,
            'logout_url'            => $logoutUrl,
            'app'                   => $app,
        ];
    }

    public function getUsersByUsernameOrEmail( array $users, $searchBy, $brokerId, $includeBroker = true ){
        $where = '';
        $params = ["broker_id" => $brokerId];
        $brokerClause = "AND c.broker =:broker_id";
        if (!$includeBroker) {
            $brokerClause = "AND c.broker !=:broker_id";
        }
        switch ($searchBy) {
            case 'email':
                if( is_array( $users ) ){
                    $emails = implode(',', $users);
                    $emails = str_replace("\'", "''", $emails);
                }
                $where = "u.email IN ($emails)
                           AND u.email != ''
                           AND u.active =1
                           $brokerClause
                           GROUP BY u.email, u.company_id";
                break;
            case 'username':
                if( is_array( $users ) ){
                    $usernames = implode(',', $users);
                    $usernames = str_replace("\'", "''", $usernames);
                }
                $where = "u.username IN ($usernames)
                           AND u.username != ''
                           AND u.active =1
                           $brokerClause
                           GROUP BY u.username, u.company_id";
                break;
            default:
                $where = "1 = 2";
                break;
        }
        $dql = "SELECT u.first_name, u.last_name, u.username, u.email, u.company_id
                 FROM Throne\Entity\User u
                 JOIN Throne\Entity\Company c WITH c.id = u.company_id
                 WHERE $where";
        $query = $this->getEntityManager()
                 ->createQuery($dql)
                 ->setParameters($params)
        ;
        $result = $query->getArrayResult();
        return (array) @$result;
    }

    public function getCount(array $filters, $search)
    {
        $whereOrderBy = $this->buildWhereOrderBy([
            'search'     => $search,
            'filters'    => $filters,
            'conditions' => ['c.is_active = 1'],
        ]);

        $dql = "
            SELECT COUNT(u.id) total_records
            FROM Throne\Entity\User u
            JOIN u.company c
            WHERE $whereOrderBy
        ";

        $query = $this->getEntityManager()
            ->createQuery($dql)
        ;

        return @$query->getArrayResult()[0]['total_records'];
    }

    public function getUsersById( $contacts, $hydrate = true  )
    {
        $dql    = "
                    SELECT c
                    FROM Throne\Entity\User c
                    WHERE c.id IN ( " . implode(",", $contacts) . " )
                ";

        $query  = $this->getEntityManager()
                ->createQuery($dql)
                ->setHint(Query::HINT_INCLUDE_META_COLUMNS, true)
            ;

        if ($hydrate) {
            return @$query->getResult();
        }

        $result = $query->getArrayResult();

        return (array) @$result;
    }

    public function deleteBatchSetPasswordRequests($contacts)
    {
        $sql    = "DELETE FROM set_password_requests WHERE contact_id in ( " . implode(",", $contacts) . " )";
        $stmt   = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute();
    }

    public function setPasswords($users, $batch_size){
        $array_ids           = array_column($users, "id");
        $obj_contacts        = $this->getUsersById($array_ids);
        $row_index           = 1;
        $save_reset          = 0;
        $array_contacts_hash = "";

        if(
            (isset($obj_contacts)) &&
            (sizeof($obj_contacts) > 0)
        ){
            $this->deleteBatchSetPasswordRequests($array_ids);

            foreach ($obj_contacts as $key => $obj_contact) {
                $request_id                                 = 'C' . \THR::generateHash($obj_contact->getId());
                $save_reset                                 = 1;
                $array_contacts_hash[$obj_contact->getId()] = $request_id;
                $throneResetRequest                         = new ThroneResetRequest;
                $throneResetRequest->setId($request_id);
                $throneResetRequest->setUser($obj_contact);
                $this->getEntityManager()->merge($throneResetRequest);
                 if (($row_index % $batch_size) === 0){
                    $this->getEntityManager()->flush();
                    //$this->getEntityManager()->detach($obj_contact);
                    $save_reset = 0;
                }
                $row_index++;
            }
            if($save_reset == 1){
                $this->getEntityManager()->flush();
            }
        }

        return $array_contacts_hash;
    }

    public function readAllUsersFromCompany($companies, $contacts = NULL){
        $condition = "";
        if($contacts != ""){
            $condition = " AND u.id in (" . $contacts . ")";
        }

        $dql = "SELECT u.id, u.company_id
                FROM Throne\Entity\User u
                JOIN u.company c
                WHERE c.id in (" . $companies . ")
                AND c.is_active = 1
                " . $condition . "
        ";

        $query = $this->getEntityManager()
                      ->createQuery($dql)
                      ->setMaxResults($limit);
        $results = $query->getArrayResult();

        $array_users = "";
        foreach ($results as $key => $result) {
            $array_users[$result["id"]] = $result["company_id"];
        }

        return $array_users;
    }

    public function readAllUsersForMarketo($companies, $contacts = NULL){
        $condition = "";
        if($contacts != ""){
            $condition = " AND u.id in (" . $contacts . ")";
        }

        $dql = "SELECT u.id, u.first_name, u.last_name, u.phone, u.email, c.client_name, c.id as companyId, b.id as brokerId,
                (CASE WHEN l.awsLocation <> '' THEN l.awsLocation ELSE 'https://s3-us-west-2.amazonaws.com/com.thinkhr/public/assets/img/thinkhr.png' END) as logo,
                (CASE WHEN pl.access <> '' THEN pl.access ELSE hb.access END) as hrhAccess,
                (CASE WHEN b.display_name <> '' THEN b.display_name ELSE b.client_name END) as brokerName, 
                (CASE WHEN hb.hotlinePhone <> '' THEN hb.hotlinePhone ELSE '877.225.1101' END) as hotlinePhoneNumber, 
                cc1.active as thinkhrLive, cc2.active as thinkhrComply, cc3.active as hasThinkhrLearn, 
                cc4.active as learnToolBox, cc5.active as hasBenefitsComplianceSuite, cc6.active as hasWorkplacePro,
                b.upsell_learn, CONCAT(e.firstName, ' ', e.lastName) as csmFullName, e.email as csmEmailAddress
                FROM Throne\Entity\User u
                JOIN Throne\Entity\Company c
                WITH c.id = u.company_id
                JOIN Throne\Entity\Company b
                WITH b.id = c.broker
                LEFT JOIN Throne\Entity\Logo l
                WITH l.companyId = b.id
                LEFT JOIN Throne\Entity\ProfileLive pl
                WITH pl.companyId = b.id
                LEFT JOIN Throne\Entity\HrhBroker hb
                WITH hb.brokerId = b.id
                LEFT JOIN Throne\Entity\ClientContract cc1
                WITH cc1.companyId = c.id AND cc1.active = 1 AND cc1.productId = 6
                LEFT JOIN Throne\Entity\ClientContract cc2
                WITH cc2.companyId = c.id AND cc2.active = 1 AND cc2.productId = 22
                LEFT JOIN Throne\Entity\ClientContract cc3
                WITH cc3.companyId = c.id AND cc3.active = 1 AND cc3.productId = 26
                LEFT JOIN Throne\Entity\ClientContract cc4
                WITH cc4.companyId = c.id AND cc4.active = 1 AND cc4.productId = 29
                LEFT JOIN Throne\Entity\ClientContract cc5
                WITH cc5.companyId = c.id AND cc5.active = 1 AND cc5.productId = 32
                LEFT JOIN Throne\Entity\ClientContract cc6
                WITH cc6.companyId = c.id AND cc6.active = 1 AND cc6.productId = 33
                LEFT JOIN Throne\Entity\THREmployee e
                WITH e.id = b.partner_manager
                WHERE c.id in (" . $companies . ")
                AND c.is_active = 1
                " . $condition . "
                GROUP BY u.id
        ";

        $query = $this->getEntityManager()
                      ->createQuery($dql)
                      ->setMaxResults($limit);
        $results = $query->getArrayResult();

        return $results;
    }

    protected function buildAclPermissions(array $permissions) {
        $em     = $this->getEntityManager();
        $sku     = $em->getRepository('Throne\Entity\Sku');
        $keys   = array();
        $skuId  = 0;
        foreach ($permissions as $permission) {
            if ((int) $permission['skuId'] != $skuId) {
                $skuId  = (int) $permission['skuId'];
                $skuKey = $sku->getSkuKey($skuId);
                $keys[] = $skuKey;
            }
            $acl = $skuKey;
            if ($permission['featureKey'] != "") {
                $acl .= '.'. $permission['featureKey'];
            }
            if ($permission['privilege'] != "") {
                $acl .= '.'. strtolower($permission['privilege']);
            }
            $keys[] = $acl;
        }
        return $keys;
    }

    public function readThroneUserByMapper($parameters)
    {
        $condition = self::buildWhereUserByMapper($parameters, true);

        $dql = "
            SELECT u
            FROM Throne\Entity\User u
            JOIN u.company c
            WHERE $condition
            AND u.role IS NOT NULL
            AND c.is_active = 1
            AND c.client_status = 1
            ORDER BY u.id DESC
        ";
        $query = $this->getEntityManager()
            ->createQuery($dql)
            ->setHint(Query::HINT_INCLUDE_META_COLUMNS, true)
            ->setMaxResults(1);
        ;

        $result = $query->getArrayResult();

        return (array) @$result[0];
    }

    public function readContactUserByMapper($parameters){

        $condition = self::buildWhereUserByMapper($parameters, false);
        
        $dql = "
            SELECT u
            FROM Throne\Entity\User u
            JOIN Throne\Entity\Company c WITH c.id = u.company_id
            JOIN Throne\Entity\Company b WITH b.id = c.broker
            WHERE $condition
            AND c.client_status = '1'
            AND u.active = '1'
            AND (c.deactivation_date IS NULL OR c.deactivation_date = '0000-00-00')
            ORDER BY u.id DESC
        ";
        
        $query = $this->getEntityManager()
                ->createQuery($dql)
                ->setHint(Query::HINT_INCLUDE_META_COLUMNS, true)
                ->setMaxResults(1);
        ;
        $result = (array) $query->getArrayResult();
        $result = @$result[0];

        return $result;
    }

    /**
     * Add description
     * ...
     *
     * @param array $parameters
     * @param boolean $isTroneUser
     * @return string
     */
    private function buildWhereUserByMapper($parameters, $isTroneUser){
        
        $condition = "";
        
        if ($parameters["ssoMapper"]){
            $condition .= "u." . $parameters["ssoMapper"] . " = '" . $parameters["email"] . "'";

        } else {
            $condition = ($isTroneUser) ? "u.username = '" . $parameters["email"] . "'" : "u.email = '" . $parameters["email"] . "'";
        }
        
        if ($parameters["companyId"]) {
            $condition .= ($isTroneUser) ? " AND u.company_id = " . $parameters["companyId"] . "" :  " AND c.id = " . $parameters["companyId"] . "";
        }

        return $condition;
    }
}

