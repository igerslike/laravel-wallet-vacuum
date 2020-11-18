<?php

namespace Bavix\WalletVacuum;

use Bavix\Wallet\Interfaces\Mathable;
use Bavix\Wallet\Interfaces\Storable;
use Bavix\Wallet\Simple\Store as SimpleStore;
use Bavix\WalletVacuum\Services\StoreService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class StoreWithoutTags implements Storable
{
    /**
     * @var array
     */
    protected $prefix;

    /**
     * @var int
     */
    protected $ttl;

    /**
     * Store constructor.
     */
    public function __construct()
    {
        $this->prefix = implode(':',config('wallet-vacuum.tags', ['wallets', 'vacuum']));
        $this->ttl = config('wallet-vacuum.ttl', 600);
    }

    /**
     * Get the balance from the cache.
     *
     * {@inheritdoc}
     */
    public function getBalance($object)
    {
        $balance = Cache::get($this->getKeyPrefixed($object));

        if ($balance === null) {
            $balance = (new SimpleStore())->getBalance($object);
        }

        return $balance;
    }

    /**
     * Increases the wallet balance in the cache array.
     *
     * {@inheritdoc}
     */
    public function incBalance($object, $amount)
    {
        if (!Cache::has($this->getKeyPrefixed($object))) {
            $this->setBalance($object, $this->getBalance($object));
        }

        Cache::increment($this->getKeyPrefixed($object),$amount);

        /**
         * When your project grows to high loads and situations arise with a race condition,
         * you understand that an extra request to
         * the cache will save you from many problems when
         * checking the balance.
         */
        return $this->getBalance($object);
    }

    /**
     * sets the cache value directly.
     *
     * {@inheritdoc}
     */
    public function setBalance($object, $amount): bool
    {
        return Cache::put(
            $this->getKeyPrefixed($object),
            app(Mathable::class)->round($amount),
            $this->ttl
        );
    }

    /**
     * @return bool
     */
    public function fresh(): bool
    {
        Redis::del(Redis::keys($this->prefix.':*'));
        return true;
    }

    /**
     * @param $object
     * @return string
     */
    public function getKeyPrefixed($object){
        $key = app(StoreService::class)->getCacheKey($object);
        return $this->prefix.':'.$key;
    }
}
