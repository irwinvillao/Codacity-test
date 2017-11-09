<?php

namespace Throne\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * AuthenticationBroker
 * 
 * @ORM\Table(name="authentication_broker")
 * @ORM\Entity(repositoryClass="Throne\Entity\Repository\AuthenticationBrokerRepository")
 
 */

class AuthenticationBroker
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="authenticationcode", type="string", length=50, nullable=true)
     */
    protected $authenticationCode;

    /**
     * @var string
     *
     * @ORM\Column(name="url", type="string", length=2500, nullable=true)
     */
    protected $url;

    /**
     * @var string
     *
     * @ORM\Column(name="createdbyuser", type="string", length=255, nullable=true)
     */
    protected $createdByUser;

    /**
     * @var string
     *
     * @ORM\Column(name="createdondate", type="string", nullable=true)
     */
    protected $createdOnDate;

    /**
     * @var string
     *
     * @ORM\Column(name="brokerid", type="integer",  nullable=false)
     */
    protected $brokerId;

    /**
     * @var string
     *
     * @ORM\Column(name="isenabled", type="string",  length=1, nullable=true)
     */
    protected $isEnabled;

    /**
     * @var string
     *
     * @ORM\Column(name="sso_mapper", type="string",  length=255, nullable=true)
     */
    protected $ssoMapper;    


 
    public function getId()
    {
        return $this->id;
    }

    public function setAuthenticationCode($authenticationCode)
    {
        $this->authenticationCode = $authenticationCode;

        return $this;
    }

    public function getAuthenticationCode()
    {
        return $this->authenticationCode;
    }

    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function setCreatedByUser($createdByUser)
    {
        $this->createdByUser = $createdByUser;

        return $this;
    }

    public function getCreatedByUser()
    {
        return $this->createdByUser;
    }

    public function setCreatedOnDate($createdOnDate)
    {
        $this->createdOnDate = $createdOnDate;

        return $this;
    }

    public function getCreatedOnDate()
    {
        return $this->createdOnDate;
    }

    public function setBrokerId($brokerId)
    {
        $this->brokerId = $brokerId;

        return $this;
    }

    public function getBrokerId()
    {
        return $this->brokerId;
    } 

    public function setIsEnabled($isEnabled)
    {
        $this->isEnabled = $isEnabled;

        return $this;
    }

    public function getIsEnabled()
    {
        return $this->isEnabled;
    }

    public function setSsoMapper($ssoMapper)
    {
        $this->ssoMapper = $ssoMapper;

        return $this;
    }

    public function getSsoMapper()
    {
        return $this->ssoMapper;
    }    
    
}