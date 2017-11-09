<?php

namespace Throne\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * CompanyMapper
 * 
 * @ORM\Table(name="app_throne_sso_company_mapper")
 * @ORM\Entity(repositoryClass="Throne\Entity\Repository\CompanyMapperRepository")
 */

class CompanyMapper
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
     * @ORM\Column(name="companyId", type="string", length=100, nullable=true)
     */
    protected $companyId;

    /**
     * @var string
     *
     * @ORM\Column(name="ssoCompanyName", type="string", length=255, nullable=true)
     */
    protected $ssoCompanyName;

    /**
     * @var string
     *
     * @ORM\Column(name="ssoMapper", type="string", length=255, nullable=true)
     */
    protected $ssoMapper;

    /**
     * @var string
     *
     * @ORM\Column(name="created", type="string", nullable=true)
     */
    protected $created;

    /**
     * @var string
     *
     * @ORM\Column(name="updated", type="string", nullable=true)
     */
    protected $updated;

    /**
     * @var string
     *
     * @ORM\Column(name="updatedBy", type="string", nullable=true)
     */
    protected $updatedBy;

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=255, nullable=true)
     */
    protected $status;

 
    public function getId()
    {
        return $this->id;
    }

    public function setCompanyId($companyId)
    {
        $this->companyId = $companyId;

        return $this;
    }

    public function getCompanyId()
    {
        return $this->companyId;
    }

    public function setSsoCompanyName($ssoCompanyName)
    {
        $this->ssoCompanyName = $ssoCompanyName;

        return $this;
    }

    public function getSsoCompanyName()
    {
        return $this->ssoCompanyName;
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

    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    public function getCreated()
    {
        return $this->created;
    }

    public function setUpdated($updated)
    {
        $this->updated = $updated;

        return $this;
    }

    public function getUpdated()
    {
        return $this->updated;
    }

    public function setUpdatedBy($updatedBy)
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    public function getUpdatedBy()
    {
        return $this->updatedBy;
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
    
}