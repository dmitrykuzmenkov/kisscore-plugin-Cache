<?php
namespace Plugin\Cache;
use Memcached;
/**
* Класс реализации методов для кэширования объектов в памяти
* Представляет из себя wrapper класса Memcached
*
* @uses \Memcached
* @link http://www.php.net/manual/en/class.memcache.php
*
* @final
* @package Core
* @subpackage Cache
*/
class Cache {
  final protected function __construct() {}

  /**
   * Подключение к серверну мемкэша
   * @return Memcached
   */
  protected static function connect() {
    static $Con;
    if (!$Con) {
      $Con = new Memcached;
      $Con->setOption(Memcached::OPT_BINARY_PROTOCOL, config('memcache.binary_protocol'));
      $Con->setOption(Memcached::OPT_COMPRESSION, config('memcache.compression'));
      $Con->setOption(Memcached::OPT_CONNECT_TIMEOUT, config('memcache.connect_timeout'));
      $Con->setOption(Memcached::OPT_RETRY_TIMEOUT, config('memcache.retry_timeout'));
      $Con->setOption(Memcached::OPT_SEND_TIMEOUT, config('memcache.send_timeout'));
      $Con->setOption(Memcached::OPT_RECV_TIMEOUT, config('memcache.recv_timeout'));
      $Con->setOption(Memcached::OPT_POLL_TIMEOUT, config('memcache.poll_timeout'));
      $Con->setOption(Memcached::OPT_PREFIX_KEY, config('memcache.key_prefix'));

      if (!$Con->addServer(config('memcache.host'), config('memcache.port'))) {
        App::error('Ошибка при попытке подключения к серверу кэша в оперативной памяти.');
      }
    }

    return $Con;
  }

  /**
   * Получение данных из кэша по ключу
   *
   * @param mixed $key
   * @param mixed $default Closure | mixed если это замыкание, то кэш записвыается
   * @return mixed кэшированное данное
   */
  public static function get($key, $default = null) {
    $items = is_string($key) ? static::connect()->get($key) : static::connect()->getMulti($key);

    // Если массив, то нужно выполнить преобразования для возвращаемых данных
    if (is_array($key)) {
      // Если возникла ошибка или же просто нет данных, то возвращаем массив
      // Т.к. было запрошен кэш по нескольким ключам
      if (!$items) {
        $items = [];
      } else {
        $map = array_flip($key);
        //$result = new SplFixedArray(sizeof($items));
        foreach ($items as $k => $item) {
          $result[$map[$k]] = $item;
        }
        unset($items);
        $items = &$result;
      }
    }
    if (false === $items) {
      $items = $default;
      if (is_string($key) && is_callable($default)) {
        static::set($key, $items = $default());
      }
    }
    return $items;
  }

  public static function getCas($key) {
    return static::connect()->getCas($key);
  }

  public static function setWithCas($key, $val, $token) {
    return static::connect()->setWithCas($key, $val, $token);
  }

  /**
   * Установка данные для ключа, перезапись в случае нахождения
   *
   * @param mixed $key Массив или строка
   * @param mixed $val
   * @param int $ttl
   * @return mixed Булевый тип или же массив с булевыми значениями для всех ключей
   */
  public static function set($key, $val, $ttl = 0) {
    assert("is_string(\$key) || is_array(\$key)");
    assert("is_int(\$ttl)");

    return is_string($key)
      ? static::connect()->set($key, $val, $ttl)
      : static::connect()->setMulti($key, $val) // $val as $ttl
    ;
  }

  /**
   * Добавление данных в кэш, если их там нет
   *
   * @param string $key
   * @param mixed $val данные для добавления в кэш
   *  @param int $ttl время жизни кэшируемого объекта
   * @return bool
   */
  public static function add($key, $val, $ttl = 0) {
    return static::connect()->add($key, $val, $ttl);
  }

  /**
  * Добавление какого-то текста к данному в конец строки
  *
  * @param string $key
  * @param string $val
  * @return bool
  */
  public static function append($key, $val) {
    return static::connect()->append($key, $val);
  }

  /**
   * Добавление какого-то текста к данному в начало строки
   *
   * @param string $key
   * @param string $val
   * @return bool
   */
  public static function prepend($key, $val) {
    return static::connect()->prepend($key, $val);
  }

  /**
   * @param string|array $key
   */
  public static function remove($key) {
    return is_string($key) ? static::connect()->delete($key) : static::connect()->deleteMulti($key);
  }

  public static function delete($key) {
    return static::remove($key);
  }

  /**
   * Увеличения счетчика на n число раз
   * Если ключа нет, он создается
   *
   * @param string $key
   * @param int $count количество, на которое необходимо увеличить счетчик
   * @return mixed Новое значение с учетом увеличения или FALSE
   */
  public static function increment($key, $count = 1) {
    if (false === $result = static::connect()->increment($key, $count)) {
      static::set($key, $count);
      return $count;
    }
    return $result;
  }

  /**
   * Уменьшение счетчика на n число раз
   *
   * @see self::increment()
   */
  public static function decrement($key, $count = 1) {
    return static::increment($key, -$count);
  }

  /**
   * Очистка всего пула кэша
   * @return bool
   */
  public static function flush() {
    return static::connect()->flush();
  }
}
