<?php

namespace Throne\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use THR\Exception\DataIntegrityException;

/**
 * @ORM\Table(name="clients")
 * @ORM\Entity(repositoryClass="Throne\Entity\Repository\CompanyRepository")
 */

class Company
{
    /**
     * @var integer
     *
     * @ORM\Column(name="clientID", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="Client_Name", type="string", nullable=true)
     */
    protected $client_name;

    /**
     * @var string
     *
     * @ORM\Column(name="Client_Type", type="string", nullable=true)
     */
    protected $client_type;

    /**
     * @var integer
     *
     * @ORM\Column(name="Client_Status", type="integer", nullable=false)
     */
    protected $client_status;

    /**
     * @var string
     *
     * @ORM\Column(name="t1_display_name", type="string", nullable=true)
     */
    protected $display_name;

    /**
     * @var string
     *
     * @ORM\Column(name="t1_email_template_id", type="string", nullable=true)
     */
    protected $email_template_id;

    /**
     * @var integer
     *
     * @ORM\Column(name="t1_is_active", type="integer", nullable=true)
     */
    protected $is_active;

    /**
     * @ORM\OneToOne(targetEntity="Company")
     *
     * @ORM\Column(name="t1_configuration_id", type="integer", nullable=true)
     */
    protected $configuration_id;

    /**
     * @var string
     *
     * @ORM\Column(name="t1_customfield1", type="string", nullable=true)
     */
    protected $custom_field1;

    /**
     * @var string
     *
     * @ORM\Column(name="t1_customfield2", type="string", nullable=true)
     */
    protected $custom_field2;

    /**
     * @var string
     *
     * @ORM\Column(name="t1_customfield3", type="string", nullable=true)
     */
    protected $custom_field3;

    /**
     * @var string
     *
     * @ORM\Column(name="t1_customfield4", type="string", nullable=true)
     */
    protected $custom_field4;

    /**
     * @var string
     *
     * @ORM\Column(name="search_help", type="string", nullable=true)
     */
    protected $search_help;

    /**
     * @var string
     *
     * @ORM\Column(name="Client_Phone", type="string", nullable=true)
     */
    protected $client_phone;

    /**
     * @var string
     *
     * @ORM\Column(name="industry", type="string", nullable=true)
     */
    protected $industry;

    /**
     * @var string
     *
     * @ORM\Column(name="companySize", type="string", nullable=true)
     */
    protected $company_size;

    /**
     * @var string
     *
     * @ORM\Column(name="producer", type="string", nullable=true)
     */
    protected $producer;

    /**
     * @var string
     *
     * @ORM\Column(name="addedBy", type="string", nullable=true)
     */
    protected $added_by;

    /**
     * @var text
     *
     * @ORM\Column(name="special_note", type="string", nullable=true)
     */
    protected $special_note;

    /**
     * @var integer
     *
     * @ORM\Column(name="enhanced_password", type="integer", nullable=true)
     */
    protected $enhanced_password;

    /**
     * @var date
     *
     * @ORM\Column(name="Client_Since", type="string", nullable=true)
     */
    protected $client_since;

    /**
     * @var integer
     *
     * @ORM\Column(name="partner_manager", type="integer", nullable=true)
     */
    protected $partner_manager;

    /**
     * @var integer
     *
     * @ORM\Column(name="upsellLearn", type="integer", nullable=true)
     */
    protected $upsell_learn;

    
    /**
     * @var string
     *
     * @ORM\Column(name="deactivationDate", type="string", nullable=true)
     */
    protected $deactivation_date;

    /**
     * @ORM\OneToOne(targetEntity="Company")
     * @ORM\JoinColumn(name="Broker", referencedColumnName="clientID")
     */
    protected $broker;

    /**
     * @ORM\OneToMany(targetEntity="CompanyBranding", mappedBy="company")
     */
    protected $branding;

    /**
     * @ORM\OneToOne(targetEntity="Configuration")
     * @ORM\JoinColumn(name="t1_configuration_id", referencedColumnName="id")
     */
    protected $configuration;

    /**
     * @ORM\OneToMany(targetEntity="Configuration", mappedBy="company")
     */
    protected $configurations;

    /**
     * @ORM\OneToMany(targetEntity="Role", mappedBy="company")
     */
    protected $roles;

    /**
     * @ORM\OneToMany(targetEntity="User", mappedBy="company")
     */
    protected $users;

    /**
     * @ORM\OneToMany(targetEntity="Document", mappedBy="company")
     */
    protected $documents;

    /**
     * @ORM\OneToOne(targetEntity="Support", mappedBy="company")
     */
    protected $support;

    /**
     * @ORM\OneToMany(targetEntity="CompanyIpWhitelist", mappedBy="company")
     */
    protected $authWhitelistIps;

    public function __construct()
    {
        $this->configurations = new ArrayCollection();
        $this->documents      = new ArrayCollection();
        $this->roles          = new ArrayCollection();
        $this->users          = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function setClientName($clientName)
    {
        $this->client_name = $clientName;

        return $this;
    }

    public function getClientName()
    {
        return $this->client_name;
    }

    public function setClientType($clientType)
    {
        $this->client_type = $clientType;

        return $this;
    }

    public function getClientType()
    {
        return $this->client_type;
    }

    public function setClientStatus($clientStatus)
    {
        $this->client_status = $clientStatus;

        return $this;
    }

    public function getClientStatus()
    {
        return $this->client_status;
    }

    public function setDisplayName($displayName)
    {
        $this->display_name = $displayName;

        return $this;
    }

    public function getDisplayName()
    {
        return $this->display_name;
    }

    public function setEmailTemplateId($emailTemplateId)
    {
        $this->email_template_id = $emailTemplateId;

        return $this;
    }

    public function getEmailTemplateId()
    {
        return $this->email_template_id;
    }

    public function setIsActive($isActive)
    {
        $this->is_active = $isActive;

        return $this;
    }

    public function getIsActive()
    {
        return $this->is_active;
    }

    public function setCustomField1($custom_field1)
    {
        $this->custom_field1 = $custom_field1;

        return $this;
    }

    public function getCustomField1()
    {
        return $this->custom_field1;
    }

    public function setCustomField2($custom_field2)
    {
        $this->custom_field2 = $custom_field2;

        return $this;
    }

    public function getCustomField2()
    {
        return $this->custom_field2;
    }

    public function setCustomField3($custom_field3)
    {
        $this->custom_field3 = $custom_field3;

        return $this;
    }

    public function getCustomField3()
    {
        return $this->custom_field3;
    }

    public function setCustomField4($custom_field4)
    {
        $this->custom_field4 = $custom_field4;

        return $this;
    }

    public function getCustomField4()
    {
        return $this->custom_field4;
    }

    public function setSearchHelp($searchHelp)
    {
        $this->search_help = $searchHelp;

        return $this;
    }

    public function getSearchHelp()
    {
        return $this->search_help;
    }

    public function setClientPhone($clientPhone)
    {
        $this->client_phone = $clientPhone;

        return $this;
    }

    public function getClientPhone()
    {
        return $this->client_phone;
    }

    public function setIndustry($industry)
    {
        $this->industry = $industry;

        return $this;
    }

    public function getIndustry()
    {
        return $this->industry;
    }

    public function setCompanySize($companySize)
    {
        $this->company_size = $companySize;

        return $this;
    }

    public function getCompanySize()
    {
        return $this->company_size;
    }

    public function setProducer($producer)
    {
        $this->producer = $producer;

        return $this;
    }

    public function getProducer()
    {
        return $this->producer;
    }

    public function setAddedBy($addedBy)
    {
        $this->added_by = $addedBy;

        return $this;
    }

    public function getAddedBy()
    {
        return $this->added_by;
    }

    public function setSpecialNote($specialNote)
    {
        $this->special_note = $specialNote;

        return $this;
    }

    public function getSpecialNote()
    {
        return $this->special_note;
    }

    public function setEnhancedPassword($enhancedPassword)
    {
        $this->enhanced_password = $enhancedPassword;

        return $this;
    }

    public function getEnhancedPassword()
    {
        return $this->enhanced_password;
    }

    public function setClientSince($clientSince)
    {
        $this->client_since = $clientSince;

        return $this;
    }

    public function getClientSince()
    {
        return $this->client_since;
    }

    public function setBroker(Company $broker = null)
    {
        $this->broker = $broker;

        return $this;
    }

    public function getBroker()
    {
        return $this->broker;
    }

    public function setBranding(CompanyBranding $branding = null)
    {
        $this->branding = $branding;

        return $this;
    }

    public function getBranding()
    {
        return $this->branding;
    }

    public function setConfiguration(Configuration $configuration = null)
    {
        $this->configuration = $configuration;

        return $this;
    }

    public function getConfiguration()
    {
        return $this->configuration;
    }

    public function getConfigurations()
    {
        return $this->configurations;
    }

    public function setDeactivationDate($deactivationDate)
    {
        $this->$deactivation_date = $deactivationDate;

        return $this;
    }

    public function getDeactivationDate()
    {
        return $this->$deactivation_date;
    }    

    public function getAdministratorRole()
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq("isAdministrator", 1))
            ->getFirstResult()
        ;

        return $this->getRoles()->matching($criteria);
    }

    public function getRoles()
    {
        return $this->roles;
    }

    public function getUsers()
    {
        return $this->users;
    }

    public function getDocuments()
    {
        return $this->documents;
    }

    public function setSupport(Support $support = null)
    {
        $this->support = $support;

        return $this;
    }

    public function getSupport()
    {
        return $this->support;
    }

    public function isSuperAdmin()
    {
        return $this->getId() === 8148;
    }

    public function getAuthWhitelistIps()
    {
        return $this->authWhitelistIps;
    }

    public function setPartnerManager($partnerManager)
    {
        $this->partner_manager = $partnerManager;

        return $this;
    }

    public function getPartnerManager()
    {
        return $this->partner_manager;
    }

    public function setUpsellLearn($upsellLearn)
    {
        $this->upsell_learn = $upsellLearn;

        return $this;
    }

    public function getUpsellLearn()
    {
        return $this->upsell_learn;
    }


    /**
     * Apply this Company's broker's Configuration to itself.
     *
     * If this is the super broker account (ThinkHR), set from the second configuration.
     *
     * @return \Throne\Entity\Company|boolean $this if successful, or false if no configurations
     *    exist for broker.
     */
    public function setConfigurationFromBroker()
    {
        // Check if Company already has a Configuration assigned
        if($this->getConfiguration()) {
            return true;
        }

        if ($this->isSuperAdmin()) {
            $configurations = $this->getBroker()->getConfigurations();

            if (!count($configurations)) {
                return false;
            }

            // TODO: is accessing the configuration this way guarantee that it's always the same
            // configuration?
            // TODO: why is the super admin getting the second configuration?
            $this->setConfiguration($this->getConfigurations()[1]);
        } else {
            $configuration = $this->getBroker()->getConfiguration();

            if (!$configuration) {
                return false;
            }

            // TODO: what does it mean to get the first broker configuration?
            $this->setConfiguration($configuration);
        }

        return true;
    }

    /**
     * Provision a new Company.
     *
     * @return \Throne\Entity\Company
     */
    public function provision()
    {
        // Set the company to active
        $this->setIsActive(1);

        // Set the Configuration from broker
        if ($this->setConfigurationFromBroker() === false) {
            throw new DataIntegrityException('No available broker configurations to assign. You must create a configuration for this broker in Throne.');
        }

        // Update company's display name from client name
        if (empty($this->getDisplayName())) {
            $this->setDisplayName($this->getClientName());
        }

        return $this;
    }

    public function isIpInWhitelist($ip)
    {
        $whitelistIps = $this->getAuthWhitelistIps();

        // If no whitelisted IPs exist, allow any IP
        if (count($whitelistIps) === 0) {
            return true;
        }

        foreach ($whitelistIps as $whitelistIp) {
            // Using ip_in_cidr from helpers/ip_helper.php
            if (ip_in_cidr($ip, $whitelistIp->getIp())) {
                return true;
            }
        }

        return false;
    }
}

