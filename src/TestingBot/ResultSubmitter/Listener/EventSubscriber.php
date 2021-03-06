<?php
/**
 * @author Henning Kvinnesland <henning@keyteq.no>
 * @since 17.11.14
 */

namespace TestingBot\ResultSubmitter\Listener;

use Behat\Behat\Context\Environment\InitializedContextEnvironment;
use Behat\Behat\EventDispatcher\Event\AfterScenarioTested;
use Behat\Behat\EventDispatcher\Event\ScenarioTested;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\MinkExtension\Context\MinkContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EventSubscriber implements EventSubscriberInterface
{
    protected $key;
    protected $secret;
    /** @var \GuzzleHttp\Client */
    protected $client;

    public function __construct($key, $secret, $client)
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->client = $client;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(ScenarioTested::AFTER => array('submitResultHandler', 0));
    }

    public function submitResultHandler(AfterScenarioTested $event)
    {
        if (!$sessionId = $this->extractSessionId($event)) {
            return;
        }

        $options = array(
            'auth' => array($this->key, $this->secret),
            'body' => array('test' => array('success' => (int)$event->getTestResult()->isPassed()))
        );

        $this->client->put('https://api.testingbot.com/v1/tests/' . $sessionId, $options);
    }

    protected function extractSessionId(AfterScenarioTested $event)
    {
        $environment = $event->getEnvironment();
        if (!$environment instanceof InitializedContextEnvironment) {
            return false;
        }

        foreach ($environment->getContexts() as $context) {
            if (!$context instanceof MinkContext) {
                continue;
            }

            $driver = $context->getSession()->getDriver();
            if (!$driver instanceof Selenium2Driver) {
                continue;
            }

            return $driver->getWebDriverSessionId();
        }

        return false;
    }
}
