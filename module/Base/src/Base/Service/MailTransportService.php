<?php

namespace Base\Service;

use Base\Manager\ConfigManager;
use RuntimeException;
use Zend\Mail\Transport\Sendmail;
use Zend\Mail\Transport\Smtp;
use Zend\Mail\Transport\SmtpOptions;

class MailTransportService extends AbstractService
{

    protected $configManager;

    protected $transport;

    public function __construct(ConfigManager $configManager)
    {
        $this->configManager = $configManager;
    }

    public function getTransport()
    {
        if (! $this->transport) {
            $mailType = $this->configManager->need('mail.type');

            switch ($mailType) {
                case 'sendmail':
                    $this->transport = new Sendmail();
                    break;
                case 'smtp':
                    $options = new SmtpOptions(array(
                        'host' => $this->configManager->need('mail.host'),
                        'connection_class' => 'plain',
                        'connection_config' => array(
                            'username' => $this->configManager->need('mail.user'),
                            'password' => $this->configManager->need('mail.pw'),
                        ),
                    ));

                    $this->transport = new Smtp();
                    $this->transport->setOptions($options);
                    break;
                case 'smtpoverssl':
                    $optionsArray = array(
                        'host' => $this->configManager->need('mail.host'),
                        'port' => 587,
                        'connection_class' => 'plain',
                        'connection_config' => array(
                            'username' => $this->configManager->need('mail.user'),
                            'password' => $this->configManager->need('mail.pw'),
                            'ssl' => $this->configManager->need('mail.ssl'),
                        ),
                    );
                    
                    $optionPort =  $this->configManager->get('mail.port');
                    if (is_numeric($optionPort)) {
                        $optionsArray['port'] = $optionPort;
                    }
                    
                    $options = new SmtpOptions($optionsArray);
                    $this->transport = new Smtp();
                    $this->transport->setOptions($options);
                    break;
                default:
                    throw new RuntimeException(sprintf($this->translate('Invalid mail type %s specified'), $mailType));
            }
        }

        return $this->transport;
    }

}