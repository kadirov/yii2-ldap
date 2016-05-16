<?php
/**
 * @package   yii2-ldap
 * @author    @author Christopher Mota <chrmorandi@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace chrmorandi\ldap\operation;

use chrmorandi\ldap\Collection\Collection;
use chrmorandi\ldap\Collection\DefaultIterator;
use chrmorandi\ldap\exceptions\ConnectionException;
use chrmorandi\ldap\operation\OperationInterface;
use chrmorandi\ldap\operation\QueryOperation;
use chrmorandi\ldap\PageControl;
use yii\helpers\ArrayHelper;

/**
 * Handles LDAP query operations.
 *
 * @author Christopher Mota <chrmorandi@gmail.com>
 * @since 1.0
 */
trait QueryOperationTrait
{

    /**
     * @var PageControl
     */
    protected $paging;

    /**
     * @param PageControl|null $paging
     */
    public function __construct(PageControl $paging = null)
    {
        $this->paging = $paging;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $allEntries = [];
//$token = $operation->name;
        /** @var QueryOperation $operation */
        //$this->paging($this->conn->getConnection)->setIsEnabled($this->shouldUsePaging());
        //$this->paging($this->conn->getConnection)->start($this->getPageSize(), $this->getSizeLimit());
        do {
            //$this->paging()->next();
           // Yii::beginProfile($token, 'chrmorandi\ldap\Connection::execute');
            $result = @call_user_func(
                $this->getLdapFunction(),
                $this->conn->getConnection(),
                ...$this->getArguments()
            );
            //$allEntries = $this->processSearchResult($result, $allEntries);

            //$this->paging()->update($result);
        } while (false);//($this->paging()->isActive());
        //$this->paging()->end();
//finally {
//            Yii::endProfile($token, 'chrmorandi\ldap\Connection::execute');
//            //$this->resetLdapControls($operation);
//        }
        
        $iterator = new DefaultIterator($this->conn, $result);
        $entries = (new Collection($iterator))->toArray();
        
        return $entries;
    }


    /**
     * Gets the base DN for a search based off of the config and then the RootDSE.
     *
     * @return string
     * @throws ConnectionException
     */
    protected function getBaseDn()
    {
        if (!empty($this->connection->getConfig()->getBaseDn())) {
            $baseDn = $this->connection->getConfig()->getBaseDn();
        } elseif ($this->connection->getRootDse()->has('defaultNamingContext')) {
            $baseDn = $this->connection->getRootDse()->get('defaultNamingContext');
        } elseif ($this->connection->getRootDse()->has('namingContexts')) {
            $baseDn =  $this->connection->getRootDse()->get('namingContexts')[0];
        } else {
            throw new ConnectionException('The base DN is not defined and could not be found in the RootDSE.');
        }

        return $baseDn;
    }

    /**
     * {@inheritdoc}
     */
    public function setOperationDefaults(OperationInterface $operation)
    {
        /** @var QueryOperation $operation */
        if (is_null($operation->getPageSize())) {
            $operation->setPageSize($this->connection->getConfig()->getPageSize());
        }
        if (is_null($operation->getBaseDn())) {
            $operation->setBaseDn($this->getBaseDn());
        }
        if (is_null($operation->getUsePaging())) {
            $operation->setUsePaging($this->connection->getConfig()->getUsePaging());
        }
        $this->parentSetDefaults($operation);
    }

    /**
     * Process a LDAP search result and merge it with the existing entries if possible.
     *
     * @param resource $result
     * @param array $allEntries
     * @return array
     * @throws ConnectionException
     */
    protected function processSearchResult($result, array $allEntries)
    {
        if (!$result) {
            throw new ConnectionException(sprintf('LDAP search failed: %s', $this->conn->getLastError()));
        }

        $entries = @ldap_get_entries($this->conn->getConnection(), $result);
        if (!$entries) {
            return $allEntries;
        }
        $allEntries['count'] = isset($allEntries['count']) ? $allEntries['count'] + $entries['count'] : $entries['count'];
        unset($entries['count']);

        return array_merge($allEntries, $entries);
    }

    /**
     * @return PageControl
     */
    protected function paging()
    {
        if (!$this->paging) {
            $this->paging = new PageControl($this->conn->getConnection);
        }

        return $this->paging;
    }

    /**
     * Based on the query operation, determine whether paging should be used.
     *
     * @return bool
     */
    protected function shouldUsePaging()
    {
        return ($this->getUsePaging() && $this->getScope() != QueryOperation::SCOPE['BASE']);
    }
}
