<?php
namespace Catapult;

/**
 * A collection object. All collection classes must
 * inherit this. It provides methods to lookup, serialize
 * and put contents in a collection. Collections
 * can represent any extended class where
 * any of those listed in models. does not have an implemention for @class Media
 *
 *
 * @class CollectionObject. Superset
 * of the collections. Provides auxilary functions
 * to manage, update and get elements within a collection
 */
class CollectionObject {

	/**
	 * either pass in infomation as array
	 * or datapacket collection identify each element key by its id
         *
         * @param data -> initial set of data
         */	 
	public function __construct($data, $quiet = TRUE)
	{
		$this->data = array();
		$this->order = array();

		if ($data instanceof DataPacketCollection) {
			$data = $data->get();
			$cnt = 0;

            foreach($data as $d) {
			      /* can we find an id? */
			      if (!(array_key_exists("id", $d)))
			      $d['id'] = Locator::Find(array("Location" => $d['location']));
			
                  if (!(in_array("id", array_keys($d))))
                       throw new \CatapultApiException(EXCEPTIONS::EXCEPTION_OBJECT_ID_NOT_PROVIDED);


                  /** is it a quiet load or do we initialize the object **/
                  /** if loading is not optimal, find better approach **/	
                  /** when we have more than the threshold warn the user in log **/
                  /** large shards of data will be very slow to reload and without waring hard to debug **/
                 if (!($quiet))
                    $this->data[$d{'id'}] = $this->get($d{'id'});
                 else
                    $this->data[$d{'id'}] = $d;

			      $this->order[$cnt] = $d{'id'};

			      $cnt ++;
           }

		} else {

			foreach ($data as $d) 
                 $data->data[$d['id']] = $d;

		}
	}

	/**
	 * Serialize the collection object as a 
         * JSON object.
	 */
	public function __toString()
	{
		return Encoder::Serialize($this->data);
	}

	/**
 	 * get the last item within
	 * a set
	 *
	 * 
	 */
	public function last()
	{
		return $this->data[$this->order[sizeof($this->order) - 1]];
	}

	/**
	 * get the first item
	 * within a set
	 *
	 */
	public function first()
	{
		return $this->data[$this->order[0]];
	}
	

        /**
	 * Get one item from the Collection
         * if item is not initiated, initiate it
         *
         * @param id -> a valid id inside this collection
         */
        public function get($id=null)
        {
                if ($id == null)
		      return $this->data;


		$cname = "Catapult\\" . $this->getName();

		$obj = new $cname;

		return $obj->get($id);
        }

	/**
	 * Before adding we must make sure the datapacket fits(schema)
         * support two fold init where
         * data can either be the initialized object
         * or raw array
         *
         * @param data -> data that is valid for this collection
	 */
        public function add($data)
        {
	      $schema = array_keys(get_object_vars(($this->data[0])));
              $schema1 = is_array($data) ? array_keys($data) : array_keys(get_object_vars($data));

	      foreach ($schema as $s) 
		if (!(in_array($s, $schema1)))
			throw new \CatapultApiException(EXCEPTIONS::WRONG_DATA_PACKET . $this->name());


              if (is_object($data))
		     $this->data[$data1->id] = $data1;
	      else
         	     $this->data[$data1['id']] = $this->get($data1['id']);
        }

	/**
	 * get a specific entity
	 * in the collection
         *
	 * @param id -> id for match
	 * @return collectionsequence
	 */
	public function find($terms /* associate array of terms */)
	{
		$terms = Ensure::Input($terms);

		$terms = $terms->get();
		$out = array();

		foreach ($terms as $k => $term) {
			foreach ($this->data as $d) {

				if (is_object($d)) 
					if ($d->$k == $term)
						$out[] = $d;

				if (is_array($d)) 
					if ($d[$k] == $term)
						$out[] = $d;
			

			}
		}

		return new CollectionSequence($out, $this->getName());
	}

	/**
	 * slow on performance. ONLY
	 * use if absolutely needed
	 * this will reload each item
	 * in a collection, individually.
         *
         * Where objects all must be initiated
         * and have get/1 ready with id
	 */
	public function reload()
	{
                  foreach ($this->data as $idx => $item)
                       $this->data[$idx] = $item->reload();
	}
}


/**
 * provide functions
 * for last/1 and first/1
 * in a collection subset
 * so example:
 * $calls->find(array("direction" => "in", "state" => "started"))->first()
 *
 * or 
 * $messages->find(array("from" => "+3030202"))
 *          ->find(array("text" => "Hello"))
 *          ->last()
 */
class CollectionSequence extends CollectionObject {
	/**
	 * make the data structure according
	 * to the type of object then retrieve
         * @param data -> set of refined data
         * @param class -> class object needs to inherit
	 */
	public function __construct($data, $class_=__CLASS__)
	{
		$is_object = array_filter($data, "is_object");

		if ($is_object) {
			$this->data = $data;
			$this->class = $class_;
			return;
		}

		$this->data = array();
		$this->class = $class_;

		foreach ($data as $d) {

			$d['quiet'] = TRUE;

			$cname = "Catapult\\" . $this->getName();
			$obj = new $cname;
			$obj->load($d);

			array_push($this->data, $obj);
		}

	}

        /**
	 * Usually defined in 
         * child class. When absent
         * resort to class name
         *
         */
	public function getName()
	{
		return $this->class;
	}

        /**
	 * Gets the first item in the
         * sequence.
         */
	public function first()
	{
		if (!(sizeof($this->data) > 0))
			return;

		return $this->data[0];
	}

        /**
	 * get the last item in 
         * a sequence.
         */
	public function last()
	{
		if (!(sizeof($this->data) > 0))
			return;

		return $this->data[sizeof($this->data) - 1];
	}
}

/**
 * Represent a of GenericOptions
 * packed as an array each datapacket should have its
 * Schema which is followed and
 * used through the get/add/val methods
 *
 * 
 * DataPacket is no more than a input based convinience
 * that can be serialized into its needed form
 * 
 * @object DataPacket
 */
final class DataPacket extends BaseUtilities {
	private $serialized = false;
	private $is_empty = false;
	private $has_id = false;
	private $dispatched = false;
	private $schema = array();

	/** 
	 * A datapacket  needs to 'cast'
	 * to string as some paramets
	 * maybe of Catapult
	 * api type
	 *
	 * @param $args -> collection of schema data
	 */
	public function __construct($args)
	{
		if ($args instanceof Parameters)
			$args = $args->data;

		$this->data = array();

		foreach ($args as $k => $arg)
			if (is_array($arg) || !method_exists($arg, "__toString"))
				$this->data[$k] = $arg;
            else 
				$this->data[$k] = (string) $arg;

	}

	/**
	 * Get the stored datapacket when
	 * this is done the data is considered to be dispatched
         *
	 * @param strict -> strict or not if it is
	 * data packet cant be retrieved twice.
	 */
	public function get($strict=FALSE) 
	{
		if ($strict && $this->dispatched)
			throw new \CatapultApiException("Already got data from packet");	

		$this->dispatched = true;

		return $this->data;
	}

	/** we need this since get only can be called once */
	public function has($key)
	{
		return isset($this->data[$key]) ? TRUE : FALSE;
	}

	/**
	 * Return a singular
	 * value from the data
	 * array
	 * @param key -> single key [schema]
	 */
	public function val($key)
	{
		if (!(isset($this->data[$key])))
			Throw new \CatapultApiException("Key not found in data packet");

		if (!(in_array($key, array_keys($this->schema))))
			Throw new \CatapultApiException("This key does not match the data packet's schema");


		return $this->data[$key];
	}

	/**
	 * Set a single key
	 * for the schema
	 *
	 * @param key -> key [within schema]
	 * @param val -> val 
	 */
	public function add($key, $val)
	{
		/*
		if (!(in_array($key, array_keys($this->schema))))
			Throw new \CatapultApiException("This key does not match the data packet's schema");
		*/

		if (is_array($key))
			foreach($key as $k)
				$this->data[$k] = $val;
		else
			if (is_array($val))
				$this->data[$key] = $val;
			else
				$this->data[$key] = (string) $val;
	}


	/**
	 * Is the data packet empty. If it is throw warning
         * otherwise return false
	 */
	public function is_empty()
	{
		if (!(sizeof($this->data) > 0))
			Throw new \CatapultApiException("Packet is empty");

		return FALSE;
	}

	/* Check if the given packet has a id. */
	public function has_id()
	{
		if (!isset($data['id']))
			return FALSE;

		return TRUE;
	}


	/**
	 * Ready the packet
	 * for encoding
	 * where encoding
	 * should be set to the
	 * interop format
	 */
	public function __toString()
	{
		return json_encode($this->data);
	}
}

/* Plural form of DataPacket */
final class DataPacketCollection {
	/**
	 * parameter MUST be a multi dimensional
	 * array. If it isnt return a singular
	 * DataPacket and warn if necessary
	 *
	 * @args -> multidimensional array with either datapackets or array
	 */
	public function __construct($args) {
		if (!BaseUtilities::is_multidimensional($args))
			return new DataPacket($args);

		$this->data = array();

		foreach ($args as $arg)
			$this->data[] = new DataPacket($arg);
	}

	/**
	 * same as datapacket
	 * traverse through all
	 * packets and make sure
	 * none have been fetched
	 * already
	 * 
         * @param strict: check for initial dispatch or not
         * @return multidimensional array
	 */
	public function get($strict=FALSE)
	{
		$data = array();

		if ($strict && !$this->dispatched)
			foreach ($this->data as $d)
				if ($d->dispatched)
					throw new \CatapultApiException("One of the packets was already dispatched");

		elseif ($strict && $this->dispatched)
			throw new \CatapultApiException("This packet collection was already dispatched");

		foreach ($this->data as $d)
			$data[] = $d->get();
		

		/* lowercase a state */			
		if (array_key_exists("state", $data))	
			$data['state'] = strtolower($data['state']);

		$this->dispatched = true;

		return $data;
	}
}

?>
