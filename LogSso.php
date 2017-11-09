<?php

namespace Throne\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Permission
 * 
 * @ORM\Table(name="logs_process_sso")
 * @ORM\Entity(repositoryClass="Throne\Entity\Repository\LogSsoRepository")
 */

class LogSso
{
    /**
     * @var integer
     *
     * @ORM\Column(name="ID", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="Server", type="string",  nullable=true)
     */
    protected $server;

    /**
     * @var string
     *
     * @ORM\Column(name="HttpUserAgent", type="string", nullable=true)
     */
    protected $httpUserAgent;

    /**
     * @var string
     *
     * @ORM\Column(name="RemoteAddr", type="string", nullable=true)
     */
    protected $remoteAddr;

    /**
     * @var string
     *
     * @ORM\Column(name="HttpReferer", type="string", nullable=true)
     */
    protected $httpReferer;

    /**
     * @var string
     *
     * @ORM\Column(name="Url", type="string", nullable=true)
     */
    protected $url;

    /**
     * @var string
     *
     * @ORM\Column(name="Date", type="string", nullable=true)
     */
    protected $date;

    /**
     * @var string
     *
     * @ORM\Column(name="brokerid", type="string", nullable=true)
     */
    protected $brokerId;

    /**
     * @var string
     *
     * @ORM\Column(name="Email", type="string", nullable=true)
     */
    protected $email;

    /**
     * @var string
     *
     * @ORM\Column(name="Auth", type="string", nullable=true)
     */
    protected $auth;


    /**
     * @var string
     *
     * @ORM\Column(name="domain", type="string", nullable=true)
     */
    protected $domain;

    /**
     * @var string
     *
     * @ORM\Column(name="parameter", type="string", nullable=true)
     */
    protected $parameter;

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="string", nullable=true)
     */
    protected $status;

    /**
     * @var string
     *
     * @ORM\Column(name="ctype", type="string", nullable=true)
     */
    protected $type;

 
    public function getId()
    {
        return $this->id;
    }

    public function setServer($server)
    {
        $this->server = $server;

        return $this;
    }

    public function getServer()
    {
        return $this->server;
    }

    public function setHttpUserAgent($httpUserAgent)
    {
        $this->httpUserAgent = $httpUserAgent;

        return $this;
    }

    public function getHttpUserAgent()
    {
        return $this->httpUserAgent;
    }

    public function setRemoteAddr($remoteAddr)
    {
        $this->remoteAddr = $remoteAddr;

        return $this;
    }

    public function getRemoteAddr()
    {
        return $this->remoteAddr;
    }

    public function setHttpReferer($httpReferer)
    {
        $this->httpReferer = $httpReferer;

        return $this;
    }

    public function getHttpReferer()
    {
        return $this->httpReferer;
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

    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    public function getDate()
    {
        return $this->date;
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

    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setAuth($auth)
    {
        $this->auth = $auth;

        return $this;
    }

    public function getAuth()
    {
        return $this->auth;
    }

    public function setDomain($domain)
    {
        $this->domain = $domain;

        return $this;
    }

    public function getDomain()
    {
        return $this->domain;
    }

    public function setParameter($parameter)
    {
        $this->parameter = $parameter;

        return $this;
    }

    public function getParameter()
    {
        return $this->parameter;
    }

    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    public function getType()
    {
        return $this->type;
    }    

    
}