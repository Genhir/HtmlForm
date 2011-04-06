<?php

if( ! version_compare(phpversion(), "5.1.0", ">=") ) throw new RuntimeException('PHP version 5.1.0 needed to use HtmlForm');

/**
 * Operations pour formulaire.
 *
 * Fourni une implementation generique des diverses operations effectue autour des formulaire HTML.
 * Prend en charge l'affichage des champs, le controle des donnees et la construction de la requete SQL.
 *
 * @author martin mauchauffee Htmlform@moechofe.com
 * @version 1.1
 */

// {{{ HtmlForm

/**
 * Classe de formulaire.
 */
class HtmlForm implements ArrayAccess, Iterator
{
  // {{{ $_name

  /**
   * Le nom de formulaire.
   *
   * Correspond a l'attribut name de la balise form.
   *
   * @var string|null
   */
  private $_name = null;

  // }}}
  // {{{ $_names

  /**
   * La liste de tous les noms des formulaire d&eacute;j&agrave; instancier
   *
   * @var array
   */
  static private $_names = array();

  // }}}
  // {{{ $_elements

  /**
   * Liste des elements du formulaire.
   *
   * Contient un tableau dont les clefs sont les noms de balises et les valeurs sont des instances de HtmlFormCustomElement
   *
   * @var array
   */
  private $_elements = array();

  // }}}
  // {{{ $_current

  /**
   * Un pointeur sur le dernier element ajout&eacute; dans le formulaire.
   *
   * @var HtmlFormCustomElement
   */
  private $_current = null;

  // }}}
  // {{{ $_method

  /**
   * La m&eacute;thode d'envoie du formulaire.
   *
   * Correpond &agrave; l'attribut method de la balise form.
   *
   * @var string
   */
  private $_method = 'post';

  // }}}
  // {{{ $_anchor

  private $_anchor = false;

  // }}}
  // {{{ $_action

  private $_action = false;

  // }}}
  // {{{ $_valid_callback

  /**
   * Fonction de callback.
   *
   * Le nom d'une fonction de callback &agrave; appeler si le formulaire est valide.
   *
   * @var string
   */
  private $_valid_callback = false;

  // }}}
  // {{{ $_send

  /**
   * Indique si le formulaire &agrave; &eacute;t&eacute; envoy&eacute;.
   *
   * @var bool
   */
  private $_send = false;

  // }}}
  // {{{ $_error

  /**
   * Indique si le formulaire &agrave; &eacute;t&eacute; envoy&eacute;.
   *
   * @var bool
   */
  private $_error = null;

  // }}}
  // {{{ $_use_request

  /**
   * Indique de lire les données client dans la super global $_REQUEST plutot que $_POST ou $_GET
   *
   * àvar boolean
   */
  private $_use_request = false;

  // }}}
  // {{{ __construct()

  /**
   * Contruit un nouveau formulaire.
   *
   * @param string Le nom du formulaire correspond &agrave; l'attribut name de la balise form.
   */
  protected function __construct( $name )
  {
    if( ! is_string($name) ) throw new InvalidArgumentException;
    if( ! preg_match('/^[_\w-]+$/', $name ) ) throw new InvalidArgumentException;

    $this->_name = $name;
  }

  // }}}
  // {{{ __call()

  /**
   * Surcharge des methodes.
   *
   * Appel une methode setxxxx de l'element precedement ajoute, ou
   * contruit un nouvel object d'un classe HtmlFormxxxxx,
   * o&ugrave; xxxx est le nom de la methode surcharge.
   *
   * @param string Le nom de la methode surchargee.
   * @param array Les parametres pass&eacute; a la methode.
   * @return HtmlForm
   */
  public function __call( $method, $arguments )
  {
    if( ! is_string($method) ) throw new InvalidArgumentException;
    if( ! is_array($arguments) ) throw new InvalidArgumentException;
/*
    if( $method == 'i18n' and $this->_current instanceof HtmlFormCustomElement and is_callable( $callback = array($this->_current,'setI18n') ) )
    {
      call_user_func_array( $callback, array_merge( array($this->_current), $arguments ) );
      return $this;
    }
/*
    if( $this->_current instanceof HtmlFormCustomElement  )
    {
      call_user_func_array( array($this->_current,'__set'), array_merge(array($method), $arguments) );
      return $this;
    }
 */
    if( $this->_current instanceof HtmlFormCustomElement and is_callable( $callback = array($this->_current,'set'.ucfirst($method)) ) )
    {
      call_user_func_array( $callback, $arguments );
      return $this;
    }

    if( ! class_exists($class_name = 'HtmlForm'.ucfirst($method)) )
      throw new BadMethodCallException(sprintf('Try to append an unexists element class: %s', $class_name));

    if( ! is_subclass_of($class_name,'HtmlFormCustomElement') )
      throw new BadMethodCallException(sprintf('The element class: %s isn\'t an child of the class: HtmlFormCustomElement', $class_name));

    return $this->appendElement( call_user_func_array( array($class_name,'hie'), array_merge(array($this,$class_name),$arguments) ) );
  }

  // }}}
  // {{{ __get()

  /**
   * Surcharge des proprietes.
   *
   * Rend publique les proprietes de la class HtmlForm.
   * Renvoie en plus une propriete cachee : error
   *
   * @param Le nom de la propriete.
   * @return mixed
   */
  public function __get( $property )
  {
    if( ! is_string($property) ) throw new InvalidArgumentException;
/*
    if( $property === 'error' )
    {
      foreach( $this->_elements as $element )
        if( $element->_error )
          return true;
      return false;
    }
 */
    if( ! method_exists( $this, 'get'.ucfirst($property) ) )
    {
      if( ! property_exists( $this, '_'.$property ) )
        throw new BadPropertyException( sprintf('The property "%s::%s" is undefined',get_class($this),$property) );
      else
      {
        $property = '_'.$property;
        return $this->$property;
      }
    }
    else
      return call_user_func( array($this,'get'.ucfirst($property)) );
  }

  // }}}
  // {{{ hie()

  /**
   * Instancie un nouveau formulaire.
   *
   * <code>
   * <?php
   *   $form1 = HtmlForm::hie('inscription');
   *   $form2 = HtmlForm::hie('identification');
   *   $form3 = HtmlForm::hie('inscription'); // Exception : Il ne peut y avoir deux formulaires avec le même nom.
   * ?>
   *
   * @param string Le nom du formulaire correspond &agrave; l'attribut name de la balise form.
   * @return HtmlForm
   */
  static public function hie( $name )
  {
    if( ! is_string($name) ) throw new InvalidArgumentException;
    if( ! preg_match('/^[_\w-]+$/', $name ) ) throw new InvalidArgumentException;
    if( isset(self::$_names[$name]) ) throw new InvalidArgumentException(sprintf('%s::%s() need a unique name to define the form',__CLASS__,__FUNCTION__));

    $form = new self( $name );
    self::$_names[$name] = $form;
    return $form;
  }

  // }}}
  // {{{ appendElement()

  /**
   * Ajout une element dans le formulaire
   *
   * @param HtmlFormCustomElement
   * @return HtmlForm
   */
  public function appendElement( HtmlFormCustomElement $element )
  {
    $this->_current = $element;
    $this->_elements[ $element->name ] = $element;
    return $this;
  }

  // }}}
  // {{{ removeElement()

  public function removeElement( HtmlFormCustomElement $element )
  {
    if( isset($this->_elements[$element->name]) )
      unset($this->_elements[$element->name]);
    return $this;
  }

  // }}}
  // {{{ selectElement()

  /***
   * Selectionne un &eacute;l&eacute;ment
   *
   * @param string Le nom de l'instance de l'&eacute;l&eacute;ment.
   * @return HtmlForm
   */
  public function selectElement( $name )
  {
    if( ! is_string($name) ) throw new InvalidArgumentException;

    if( ! isset($this->_elements[ $name ]) ) throw new OutOfBoundsException;

    $this->_current = $this->_elements[ $name ];

    return $this;
  }

  // }}}
  // {{{ hiddenFrom()

  /**
   * Importe les champs du formulaire passé en paramètre.
   * Tous les champs du formulaire passé en paramètre seront ajouté dans le formulaire courant. Les valeurs envoyés par le client seront récupérées et les champs seront masqués à l'affichage. Cette méthode est courament utilisée pour fabriquer de nouvelle page à un formulaire.
   * Params:
   *    $form = Le formulaire pour lequel on souhaite importer les champs.
   * Returns:
   *    HtmlForm = Le forumailre courant.
   */
  public function hiddenFrom( HtmlForm $form )
  {
    foreach( $form as $element )
      //if( ! $element instanceof HtmlFormSubmit )
      {
        $element->default = $element->value;
        $element->hidden = true;
        $this->appendElement( $element );
      }

    return $this;
  }

  // }}}
  // {{{ setPost()

  /**
   * Indique d'envoyer le formulaire en POST
   *
   * @return HtmlForm
   */
  public function setPost()
  {
    $this->_method = 'post';
    return $this;
  }

  // }}}
  // {{{ getPost()

  /**
   * Indique d'envoyer le formulaire en POST
   *
   * @return HtmlForm
   */
  public function getPost()
  {
    return $this->setPost();
  }

  // }}}
  // {{{ setGet()

  /**
   * Indique d'envoyer le formulaire en GET
   *
   * @return HtmlForm
   */
  public function setGet()
  {
    $this->_method = 'get';
    return $this;
  }

  // }}}
  // {{{ getGet()

  /**
   * Indique d'envoyer le formulaire en GET
   *
   * @return HtmlForm
   */
  public function getGet()
  {
    return $this->setGet();
  }

  // }}}
  // {{{ useRequest()

  /**
   * Indique de récupérer les valeurs clients grâce à la superglobals $_REQUEST
   *
   * @return HtmlForm
   */
  public function useRequest( $request = true )
  {
    if( ! is_bool($request) ) throw new InvalidArgumentException(sprintf('%s::%s() need a boolean for his 1st parameter',__CLASS__,__FUNCTION__));

    $this->_use_request = $request;
    return $this;
  }

  // }}}
  // {{{ setAnchor()

  public function setAnchor( $anchor )
  {
    if( ! is_string($anchor) ) throw new InvalidArgumentException;

    $this->_anchor = $anchor;

    return $this;
  }

  // }}}
  // {{{ setAction()

  public function setAction( $action )
  {
    if( ! is_string($action) ) throw new InvalidArgumentException;

    $this->_action = $action;

    return $this;
  }

  // }}}
  // {{{ current()

  /**
   * @ignore
   */
  public function current()
  {
    return current($this->_elements)->currentElement();
  }

  // }}}
  // {{{ next()

  /**
   * @ignore
   */
  public function next()
  {
    current($this->_elements)->nextElement();
    if( ! current($this->_elements)->elementIsValid() )
      next($this->_elements);
  }

  // }}}
  // {{{ key()

  /**
   * @ignore
   */
  public function key()
  {
    return current($this->_elements)->elementKey();
  }

  // }}}
  // {{{ rewind()

  /**
   * @ignore
   */
  public function rewind()
  {
    foreach( $this->_elements as $element )
      $element->rewindElements();
    reset($this->_elements);
  }

  // }}}
  // {{{ valid()

  /**
   * @ignore
   */
  public function valid()
  {
    return (bool)current($this->_elements);
  }

  // }}}
  // {{{ offsetExists()

  /**
   * @ignore
   */
  public function offsetExists( $offset )
  {
    if( ! is_string($offset) ) throw new InvalidArgumentException;

    return isset($this->_elements[$offset]);
  }

  // }}}
  // {{{ offsetGet()

  /**
   * @ignore
   */
  public function offsetGet( $offset )
  {
    if( ! is_string($offset) ) throw new InvalidArgumentException(sprintf('%s::%s() need a string for his 1st parameter.',__CLASS__,__FUNCTION__));

    if( ! isset($this->_elements[$offset]) ) throw new OutOfBoundsException(sprintf('Try to acces to an undefined element: %s',(string)$offset));

    return $this->_elements[$offset];
  }

  // }}}
  // {{{ offsetSet()

  /**
   * @ignore
   */
  public function offsetSet( $offset, $value )
  {
    if( ! is_string($offset) ) throw new InvalidArgumentException;
    if( ! $value instanceof HtmlFormCustomElement ) throw new InvalidArgumentException;

    $this->_elements[$offset] = $value;
  }

  // }}}
  // {{{ offsetUnset()

  /**
   * @ignore
   */
  public function offsetUnset( $offset )
  {
    if( ! is_string($offset) ) throw new InvalidArgumentException;

    if( ! isset($this->_elements[$offset]) ) throw new OutOfBoundsException(sprintf('Try to acces to an undefined index: %.',$offset));

    unset( $this->_elements[$offset] );
  }

  // }}}
  // {{{ onValid()

  /**
   * Sp&eacute;cifie une fonction de callback.
   *
   * Cette fonction sera appel&eacute; si le formulaire est valid&eacute;.
   *
   * @param string Une fonction de callback valide.
   * @return HtmlForm
   */
  public function onValid( $function )
  {
    if( ! is_callable($function) ) throw new InvalidArgumentException( sprintf('The function "%s" isn\'t callable.',print_r($function,true)) );

    $this->_valid_callback = $function;

    return $this;
  }

  // }}}
  // {{{ isSent()

  public function isSent()
  {
    foreach( $this as $element )
      if( $element instanceof HtmlFormSubmit and ! is_null($element->value) and $this->name === $element->name )
        $this->_send = true;

    return $this->_send;
  }

  // }}}
  // {{{ unvalidate()

  public function unvalidate()
  {
    $this->_error = null;

    return $this;
  }

  // }}}
  // {{{ isValid()

  /**
   * Valide le formulaire et retourne le resultat.
   *
   * Renvoie true si le formulaire est valide, sinon false.
   *
   * @return boolean
   */
  public function isValid()
  {
    $this->isSent();

    if( ! $this->_send )
      return false;

    if( ! is_null($this->_error) )
      return ! $this->_error;
    else
      $this->_error = false;

    foreach( $this as $element )
    {
      if( ! $element->required )
      {
        if( is_array($element->value) and $element->value )
          continue;
        elseif( (string)$element->value == '' )
          continue;
      }
      if( $element instanceof HtmlFormFile )
        $this->_error |= $element->ifError( $element->value['tmp_name'], $this );
      elseif( is_array($element->value) )
      {
        foreach( $element->value as $k => $v )
          $this->_error |= $element->ifError( (string)$v, $this );
      }
      else
        $this->_error |= $element->ifError( (string)$element->value, $this );
    }

    return ! $this->_error;
  }

  // }}}
  // {{{ doValid()

  /**
   * Lance la validation.
   *
   * @return HtmlForm
   */
  public function doValid( $function = false )
  {
    $new = null;

    if( $this->isValid() and is_string($function) )
    {
      if( ! is_callable($function) ) throw new InvalidArgumentException(sprintf('The function "%s" isn\'t callable.',print_r($function,true)));
      else
        $new = call_user_func( $function, $this );
    }
    elseif( $this->isValid() and $this->_valid_callback )
      $new = call_user_func( $this->_valid_callback, $this );

    if( $new instanceof HtmlForm and $new !== $this )
      return $new;
    else
      return $this;
  }

  // }}}
  // {{{ display()

  /**
   * @reserved
   */
  public function display()
  {
    return $this;
  }

  // }}}
  // {{{ fetch()

  /**
   * @reserved
   */
  public function fetch()
  {
    return $this;
  }

  // }}}
  // {{{ mysqlSet()

  /**
   * Retourne une portion de requ&ecirc;te compatible MYSQL.
   *
   * Retourne une portion de requ&ecirc;te MYSQL pr&egrave;te &agrave; &ecirc;tre ajout&eacute;e dans un INSERT, un UPDATE ou un REPLACE contenant un SET.
   *
   * Utilisation avec une resource MYSQL
   * <code>
   * <?php
   *   $link = mysql_connect( 'localhost', 'root', '' );
   *   mysql_select_db( $link, 'base' );
   *   if( $set = $form->mysqlSet($link) ) // $form est une instance de HtmlForm
   *     $query = 'UPDATE table SET '.$set;
   * ?>
   * </code>
   *
   * Utilisation avec une instance de MYSQLI
   * <code>
   * <?php
   *   $db = new mysqli( 'localhost', 'root', '', 'base' );
   *   if( $set = $form->mysqlSet($db) ) // $form est une instance de HtmlForm
   *     $query = 'UPDATE table SET '.$set;
   * ?>
   * </code>
   *
   * Utilisation avec un object contenant une m&eacute;thode escape()
   * <code>
   * <?php
   *   class db
   *   {
   *     function escape( $value )
   *     {
   *       return my_escape( $value );
   *     }
   *   }
   *   $db = new db;
   *   if( $set = $form->mysqlSet($db) ) // $form est une instance de HtmlForm
   *     $query = 'UPDATE table SET '.$set;
   * ?>
   * </code>
   *
   * @param resource|object|null Une resource ou un object.
   * @return string|false Retourne la portion de requ&ecirc;te ou false en cas d'&eacute;chec.
   */
  public function mysqlSet( $link = null )
  {
    if( ! is_resource($link) and ! is_null($link) and ! is_object($link) )
      throw new InvalidArgumentException(sprintf('%s::%s() need a optional resource or object parameter.', __CLASS__, __FUNCTION__) );

    $maps = array();
    foreach( $this as $e )
    {
      if( is_string($e->map) )
      {
        if( ! is_string($value = self::mysqlEscape( (string)$e->export, $link )) )
          return false;
        $maps[$e->map] = $value;
      }
    }

    $query = '';
    foreach( $maps as $map => $value )
      $query .= ", `{$map}`='{$value}'";

    return 'SET '.substr($query,2);
  }

  // }}}
  // {{{ mysqlValues()

  /**
   * Retourne une portions de requ&ecirc;te compatible MYSQL.
   *
   * Retourne une portion de requ&ecirc;te MYSQL pr&egrave;te &agrave; &ecirc;tre ajout&eacute;e dans un INSERT, un UPDATE ou un REPLACE contenant un VALUES.
   *
   * Voir {@link mysqlSet()}
   *
   * @param resource|object|null Une resource ou un object.
   * @return string|false Retourne la portion de requ&ecirc;te ou false en cas d'&eacute;chec.
   */
  public function mysqlValues( $link = null )
  {
    if( ! is_resource($link) and ! is_null($link) and ! is_object($link) )
      throw new InvalidArgumentException(sprintf('%s::%s() need a optional resource or object parameter.', __CLASS__, __FUNCTION__) );

    $fields = '';
    $values = '';
    foreach( $this as $e )
      if( is_string($e->map) )
      {
        if( ! is_string($value = self::mysqlEscape( (string)$e->value, $link )) )
          return false;
        $fields .= ", `{$e->map}`";
        $values .= ", '{$value}'";
      }

    return '('.substr($fields,2).') VALUES('.substr($values,2).')';
  }

  // }}}
  // {{{ mysqlDuplicateValues()

  /**
   * Retourne une portions de requ&ecirc;te compatible MYSQL.
   *
   * Retourne une portion de requ&ecirc;te MYSQL pr&egrave;te &agrave; &ecirc;tre ajout&eacute;e dans un ON DUPLICATE KEY UPDATE.
   *
   * Voir {@link mysqlSet()}
   *
   * @return string Retourne la portion de requ&ecirc;te ou false en cas d'&eacute;chec.
   */
  public function mysqlDuplicateValues()
  {
    $query = '';
    foreach( $this as $e )
      if( is_string($e->map) )
      {
        $query .= ", `{$e->map}`=VALUES(`{$e->map}`)";
      }

    return substr($query,2);
  }

  // }}}
  // {{{ mysqlEscape()

  /**
   * Escape une chaine pour mysql.
   *
   * @param string La cha&icirc;ne &agrave; &eacute;chapper.
   * @param resource|object|null Une resource ou un object.
   * @return string|false
   */
  static private function mysqlEscape( $value, $link = null )
  {
    if( is_object($link) and class_exists('mysqli') and $link instanceof mysqli )
      return $link->real_escape_string($value);

    elseif( is_object($link) and is_callable( array($link,'escape') ) and is_string($result = call_user_func( array($link,'escape'), $value )) )
      return $result;

    elseif( is_resource($link) and (get_resource_type($link) === 'mysql link' or get_resource_type($link) === 'mysql link persistent') )
      return mysql_real_escape_string($value, $link);

    if( function_exists('mysql_real_escape_string') and ! $result = @mysql_real_escape_string($value) )
    {
      return $result;
    }

    if( function_exists('mysql_escape_string') )
      return mysql_escape_string($value);

    return false;
  }

  // }}}
}

// }}}
// {{{ HtmlFormCustomElement

abstract class HtmlFormCustomElement
{
  // {{{ $_form

  /**
   * L'instance du forumlaire
   *
   * @var HtmlForm
   */
  protected $_form = null;

  // }}}
  // {{{ $_name

  /**
   * Le nom d'element HTML dans le formulaire
   *
   * Correspond &agrave; l'attribut name de l'element input, button, select ou textarea.
   *
   * @var string|null
   */
  protected $_name = null;

  // }}}
  // {{{ $_label

  /**
   * Le label du champ.
   *
   * @var string
   */
  protected $_label = null;

  // }}}
  // {{{ $_id

  /**
   * Un identifiant unique permanent (ou pas)
   *
   * @var string
   */
  protected $_id = null;

  // }}}
  // {{{ hie()

  /**
   * Instancie un element
   *
   * @param HtmlForm
   * @param string Le nom de la classe &agrave; instancier
   * @param string Le nom de l'&eacute;l&eacute;ment
   * @return HtmlFormCustomElement
   */
  static public function hie( HtmlForm $form, $class_name )
  {
    if( ! is_string($class_name) ) throw new InvalidArgumentException(sprintf('%s::%s need a string for his 2nd parametre.',__CLASS__,__FUNCTION__));

    if( func_num_args() > 2 ) $name = func_get_arg(2); else $name = null;

    if( ! is_string($name) and ! is_null($name) ) throw new InvalidArgumentException(sprintf('%::%s need a string for his 3rd parameter.',__CLASS__,__FUNCTION__));

    return new $class_name( $form, $name );
  }

  // }}}
  // {{{ __construct()

  /**
   * Construit un nouvel element pour le formulaire.
   *
   * @param HtmlForm
   * @param string|null Le nom de l'element.
   */
  protected function __construct( HtmlForm $form, $name = null )
  {
    $this->setForm( $form );

    if( is_null($name) )
      $this->setID( substr(md5(uniqid()),0,4) );

    if( is_null($name) )
    {
      $this->init( $form );
      return;
    }

//    if( ! is_string($name) ) throw new InvalidArgumentException;

    $this->setName( $name );

    $this->init( $form );
  }

  // }}}
  // {{{ __get()

  /**
   * Surcharge les proprietes.
   *
   * @param string Le nom de la propriete.
   * @return mixed
   */
  public function __get( $property )
  {
    if( ! is_string($property) ) throw new InvalidArgumentException;

    if( $property == 'class' )
      return get_class($this);
    elseif( substr($property,0,3) == 'isA' and isset($property[3]) and class_exists($class_name = 'HtmlForm'.ucfirst(substr($property,3))) )
      return get_class($this) == $class_name or is_subclass_of($this, $class_name);

    elseif( ! method_exists( $this, 'get'.ucfirst($property) ) )
    {
      if( ! property_exists( $this, '_'.$property ) )
        throw new BadPropertyException( sprintf('The property "%s::%s" is undefined',get_class($this),$property) );
      else
      {
        $property = '_'.$property;
        return $this->$property;
      }
    }
    else
      return call_user_func( array($this,'get'.ucfirst($property)) );
  }

  // }}}
  // {{{ __set()

  /**
   * Surharge les propr&eacute;t&eacute;s.
   *
   * Permet de modifier les propri&eacute;t&eacute;s.
   *
   * @param string Le nom de la propri&eacute;t&eacute;.
   * @param mixed La nouvelle valeur.
   */
  public function __set( $property, $value )
  {
    if( ! is_string($property) ) throw new InvalidArgumentException;

    if( $property === 'class' )
      return get_class($this);

    if( ! is_callable( array($this,'set'.ucfirst($property)) ) ) throw new BadPropertyException( sprintf('The property "%s::%s" is undefined',get_class($this),$property) );

    call_user_func( array($this,'set'.ucfirst($property)), $value );
  }

  // }}}
  // {{{ __isset()

  /**
   * Surcharge les propriétés.
   *
   * Permet d'utiliser la structure isset() sur les propriétés.
   *
   * @param string Le nom de la propriété.
   * @param boolean
   */
  public function __isset( $property )
  {
    try
    {
      $this->$property;
    }
    catch( BadPropertyException $e )
    {
      return false;
    }
    return true;
  }

  // }}}
  // {{{ setForm()

  public function setForm( HtmlForm $form )
  {
    $this->_form = $form;
  }

  // }}}
  // {{{ setName()

  /**
   * Modifie le nom du champ.
   *
   * @param string
   * @return HtmlFormCustomElement
   */
  public function setName( $name )
  {
    if( ! is_string($name) ) throw new InvalidArgumentException;
    if( substr($name,-2)=='[]' ) throw new InvalidArgumentException( sprintf('Element "%s" cannot have an array name',$name) );

    $this->_name = $name;
  }

  // }}}
  // {{{ setLabel()

  /**
   * Modifie le label du champ.
   *
   * @param string|false
   * @return HtmlFormCustomElement
   */
  public function setLabel( $label )
  {
    if( ! is_string($label) and ! $label === false ) throw new InvalidArgumentException(sprintf('%s::%s() need a string or a false value for his 1st parameter',__CLASS__,__FUNCTION__));

    $this->_label = preg_replace_callback('/\\$(\\w+)\\b/', array($this,'replaceProperty'), $label );
  }

  // }}}
  // {{{ setID()

  /**
   * Modifie l'identifiant unique.
   *
   * @param string
   * @return HtmlFormElement
   */
  public function setID( $id )
  {
    if( ! is_string($id) ) throw new InvalidArgumentException;

    $this->_id = $id;

    return $this;
  }

  // }}}
  // {{{ init()

  protected function init( HtmlForm $form )
  {
    if( is_null($this->name) )
    {
      $i=0; while( isset($form[$this->class.++$i]) ) null;
      $this->setName( $this->class.$i );
    }

    if( is_null($this->label) )
      $this->setLabel( ucfirst($this->name) );
  }

  // }}}
  // {{{ replaceProperty()

  protected function replaceProperty( $match )
  {
    if( isset($this->$match[1]) )
    {
      if( is_array($this->$match[1]) )
        return implode(', ',$this->$match[1]);
      elseif( (string)$this->$match[1] )
        return (string)$this->$match[1];
      else
        return '$'.$match[1];
    }
    else
      return '$'.$match[1];
  }

  // }}}
  // {{{ rewindElements()

  /**
   * @ignore
   */
  abstract function rewindElements();

  // }}}
  // {{{ elementIsValid()

  /**
   * @ignore
   */
  abstract function elementIsValid();

  // }}}
  // {{{ currentElement()

  /**
   * @ignore
   */
  abstract function currentElement();

  // }}}
  // {{{ nextElement()

  /**
   * @ignore
   */
  abstract function nextElement();

  // }}}
  // {{{ elementKey()

  /**
   * @ignore
   */
  abstract function elementKey();

  // }}}
  // {{{ getRemove()

  protected function getRemove()
  {
    $result = $this;
    $this->form->removeElement( $this );
    return $result;
  }

  // }}}
}

// }}}
// {{{ HtmlFormElement

/**
 * Classe de champ de champ evolue.
 *
 * Derivee de HtmlFormCustomElement, un object HtmlFormElement est pret a l'emploie.
 *
 * Il apporte de nombre fonctionnalitées de base utilisées par les décorateurs, comme les ID uniques et les chaînes de traductions.
 */
class HtmlFormElement extends HtmlFormCustomElement
{
  // {{{ $_error

  /**
   * L'état de l'erreur ou le message.
   *
   * @var string|boolean
   */
  protected $_error = null;

  // }}}
  // {{{ $_valid

  private $_valid = true;

  // }}}
  // {{{ $_required

  /**
   * Indique si l'element est obligatoire.
   *
   * Peut contenir un message d'erreur, si l'element est manquant.
   *
   * @var string|boolean
   */
  protected $_required = false;

  // }}}
  // {{{ $_check

  /**
   * Liste des v&eacute;rifications.
   *
   * Peut contenir des expressions rationnelles et des fonctions de callback
   *
   * @var array
   */
  protected $_check = array();

  // }}}
  // {{{ $_map

  /**
   * Le nom du champ dans la table de la base de donn&eacute;e.
   *
   * @var string
   */
  protected $_map = null;

  // }}}
  // {{{ $_alert

  /**
   * Le ou les messages d'erreur.
   *
   * @var string|array
   */
  protected $_alert = null;

  // }}}
  // {{{ $_i18n

  /**
   * L'object pour l'internationalisation;
   *
   * @var HtmlFormI18n|array
   */
  protected $_i18n = null;

  // }}}
  // {{{ $_readonly

  protected $_readonly = false;

  // }}}
  // {{{ $_default

  /**
   * Une valeur par d&eacute;faut.
   *
   * @var string|array|boolean
   */
  protected $_default = null;

  // }}}
  // {{{ $_override

  protected $_override = null;

  // }}}
  // {{{ getError()

  public function getError()
  {
    return preg_replace_callback('/\\$(\\w+)\\b/', array($this,'replaceProperty'), $this->_error );
  }

  // }}}
  // {{{ setValue()

  /**
   * todo allow array
   */
  public function setValue( $value )
  {
    if( ! is_string($value) and ! is_null($value) ) throw new InvalidArgumentException(sprintf('%s::%s() need a string for his 1st parameter',__CLASS__,__FUNCTION__));

    if( $this->form->method == 'get' )
      $_REQUEST[$this->name] = $_GET[$this->name] = $value;
    elseif( $this->form->method == 'post' )
      $_REQUEST[$this->name] = $_POST[$this->name] = $value;
    else
      $_REQUEST[$this->name] = $value;

    return $this;
  }

  // }}}
  // {{{ getValue()

  protected function getValue()
  {
    if( $this->form->use_request and isset($_REQUEST[$this->name]) )
      return $_REQUEST[$this->name];
    if( $this->form->method == 'get' and isset($_GET[$this->name]) )
      return urldecode($_GET[$this->name]);
    elseif( $this->form->method == 'post' and isset($_POST[$this->name]) )
      return $_POST[$this->name];
    elseif( isset($_REQUEST[$this->name]) )
      return $_REQUEST[$this->name];
    else
      return null;
  }

  // }}}
  // {{{ getExport()

  protected function getExport()
  {
    return $this->getValue();
  }

  // }}}
  // {{{ unsetValue()

  protected function unsetValue()
  {
    if( isset($_POST[$this->name]) )
      unset( $_POST[$this->name] );
    if( isset($_GET[$this->name]) )
      unset( $_GET[$this->name] );
    if( isset($_REQUEST[$this->name]) )
      unset( $_REQUEST[$this->name] );
  }

  // }}}
  // {{{ autoslashes()

  /**
   * Enl&egrave;ve les anti-slashes.
   *
   * @param string|array
   * @return string|array
   */
  static protected function autoslashes( $value )
  {
    if( is_array($value) )
    {
      foreach( $value as $k => $v )
        $value[$k] = self::autoslashes($v);
      return $value;
    }
    elseif( is_string($value) )
    {
      if( get_magic_quotes_gpc() )
        return stripslashes($value);
      else
        return $value;
    }
    else
      return null;
  }

  // }}}
  // {{{ __get()

  /**
   * Surcharge les proprietes.
   *
   * @param string Le nom de la propri&eacute;t&eacute;.
   * @return mixed
   */
  public function __get( $property )
  {
    if( ! is_string($property) ) throw new InvalidArgumentException;

    if( $this->_override and is_callable( $callback = array( $this->_override,'getOverrided'.ucfirst($property)) ) )
      return call_user_func( $callback );

    if( $property === 'value' and $this->_form->send )
      return self::autoslashes($this->getValue());
    elseif( $property === 'value' and $this->default )
      return $this->default;
    elseif( $property === 'value' )
      return null;
    elseif( $property === 'first' and $this->_override instanceof HtmlFormGroupedElements )
      return $this->_override->isFirst( $this );
    elseif( $property === 'last' and $this->_override instanceof HtmlFormGroupedElements )
      return $this->_override->isLast( $this );
    elseif( $property === 'first' or $property === 'last' )
      return null;

    return parent::__get( $property );
  }

  // }}}
  // {{{ __toString()

  /**
   * Surcharge pour l'affichage.
   */
  public function __toString()
  {
    return $this->__get('html');
  }

  // }}}
  // {{{ ifError()

  /**
   * Verifie si l'element est valid.
   *
   * Retourne true en cas d'erreur sinon false.
   *
   * @param string La valeur de l'element envoy&eacute; par le client.
   * @param HtmlForm Le formulaire.
   * @return boolean
   */
  public function ifError( $value, HtmlForm $form )
  {
    if( ! is_string($value) ) throw new InvalidArgumentException;

    if( $this instanceof HtmlFormFile and $this->required and $value=='' )
      return (bool)($this->_error = $this->required);
    elseif( $this->required and $this->value=='' )
      return (bool)($this->_error = $this->required);

    $this->_error = $error = false;

    foreach( $this->check as $check )
    {
      if( is_callable($check) )
      {
        $return = call_user_func( $check, $this, $form );
        if( ! is_string($return) and ! is_bool($return) and ! is_integer($return) )
          throw new UnexpectedValueException( 'Callback: '.print_r($check,true).' must return a boolean, a string or an integer.' );
        if( (is_string($return) or is_integer($return) or $return === true) and is_array($this->alert) and isset($this->alert[$return]) )
          $error |= (bool)($this->_error = $this->alert[$return]);
        elseif( $return and is_string($this->alert) )
          $error |= (bool)($this->_error = $this->alert);
        elseif( $return )
          $error |= (bool)($this->_error = $return);
      }

      elseif( is_string($check) and ! preg_match( $check, $value ) )
      {
        if( is_string($this->alert) )
          $error |= (bool)($this->_error = $this->alert);
        elseif( is_array($this->alert) and isset($this->alert[true]) )
          $error |= (bool)($this->_error = $this->alert[true]);
        else
          $error |= (bool)($this->_error = true);
      }

      else
        $error |= ($this->_error = false);
    }

    return (bool)$error;
  }

  // }}}
  // {{{ setRequired()

  /**
   * Modifie le message si le champ est obligatoire.
   *
   * @param string|boolean
   * @param string|null
   * @return HtmlFormElement
   */
  public function setRequired( $required = true, $name = null )
  {
    if( ! is_string($required) and ! is_bool($required) ) throw new InvalidArgumentException;

    if( $required === true and is_string($name) )
      $this->_required = 'Missing field: '.$name;
    elseif( $required === true )
      $this->_required = 'Missing field: '.$this->name;
    else
      $this->_required = $required;
  }

  // }}}
  // {{{ setCheck()

  /**
   * Ajoute une verification.
   *
   * @param string Une expression rationnelle, ou une fonction de callback.
   * @return HtmlFormElement
   */
  public function setCheck( $check )
  {
    if( ! is_string($check) and ! is_array($check) ) throw new InvalidArgumentException;

    $args = func_get_args();

    if( isset($args[1]) and $args[1] === true )
    {
      unset($args[1]);
      array_unshift( $this->_check, $check );
    }
    else
      $this->_check[] = $check;

    if( count($args) > 1 )
    {
      array_shift($args);
      call_user_func_array( array($this,__FUNCTION__), $args );
    }

    return $this;
  }

  // }}}
  // {{{ setMap()

  /**
   * Modifie le nom du champ de la table de la base de donn&eacute;e.
   *
   * @param string|null
   * @return HtmlFormElement
   */
  public function setMap( $map )
  {
    if( ! is_string($map) and ! is_null($map) ) throw new InvalidArgumentException(sprintf('%s::%s() need a string for his 1st parameter',__CLASS__,__FUNCTION__));

    $this->_map = $map;

    return $this;
  }

  // }}}
  // {{{ setAlert()

  /**
   * Modifie le message ou la liste des messages d'erreur.
   *
   * @param string|array
   * @return HtmlFormElement
   */
  public function setAlert( $alert, $key = null )
  {
    if( ! is_string($alert) and ! is_array($alert) ) throw new InvalidArgumentException(sprintf('%s::%s() need a string or a array for his 1st parameter',__CLASS__,__FUNCTION__));
    if( ! is_string($key) and ! is_null($key) ) throw new InvalidArgumentException(sprintf('%s::%s() need a string for his 2nd parameter',__CLASS__,__FUNCTION__));

    if( is_string($alert) and is_string($key) )
    {
      if( ! is_array($this->_alert) )
        $this->_alert = array(true=>$this->_alert);
      $this->_alert[$key] = preg_replace_callback('/\\$(\\w+)\\b/', array($this,'replaceProperty'), $alert );
    }
    elseif( is_array($alert) )
    {
      foreach( $alert = self::arrayifize($alert) as $k => $v )
        $alert[$k] = preg_replace_callback('/\\$(\\w+)\\b/', array($this,'replaceProperty'), $v );
      $this->_alert = $alert;
    }
    elseif( is_string($alert) )
      $this->_alert = preg_replace_callback('/\\$(\\w+)\\b/', array($this,'replaceProperty'), $alert );

    return $this;
  }

  // }}}
  // {{{ setI18n()

  /**
   * Modifie les données de traduction.
   *
   * @param HtmlFormElement
   * @param Iterator|array|string
   * @return HtmlFormElement
   */
  public function setI18n( $i18n )
  {
    if( $i18n instanceof Iterator or is_array($i18n) )
    {
      $this->_i18n = $i18n;
      return $this;
    }

    if( ! is_string($i18n) ) throw new InvalidArgumentException;

    if( class_exists($class_name = $i18n) )
      null;
    elseif( ! class_exists($class_name = $this->class.ucfirst($i18n).'I18n') )
      throw new BadMethodCallException(sprintf('Try to use an unexists i18n class: %s', $class_name));

    if( ! is_subclass_of($class_name,'HtmlFormI18n') )
      throw new BadMethodCallException(sprintf('The element class: %s isn\'t an child of the class: HtmlFormI18n', $class_name));

    $this->_i18n = new $class_name( $this );

    return $this;
  }

  // }}}
  // {{{ setDefault()

  /**
   * Modifie le label du champ.
   *
   * @param string|array|boolean|null
   * @return HtmlFormElement
   */
  public function setDefault( $default )
  {
    if( ! is_scalar($default) and ! is_array($default) and ! is_null($default) ) throw new InvalidArgumentException(sprintf('%s::%s() need a scalar or an array for his 1st parameter',__CLASS__,__FUNCTION__));

    if( is_null($default) or is_bool($default) )
      $this->_default = $default;
    else
      $this->_default = preg_replace_callback('/\\$(\\w+)\\b/', array($this,'replaceProperty'), $default );

    return $this;
  }

  // }}}
  // {{{ setReadonly()

  public function setReadonly( $readonly = true )
  {
    if( ! is_bool($readonly) ) throw new InvalidArgumentException(sprintf('%s::%s() need a boolean for his 1st paramter',__CLASS__,__FUNCTION__));

    $this->_readonly = $readonly;

    return $this;
  }

  // }}}
  // {{{ setOverride()

  public function setOverride( HtmlFormCustomElement $override )
  {
    $this->_override = $override;

    return $this;
  }

  // }}}
  // {{{ init()

  /**
   * Phase d'initialisation d'un champ.
   *
   * @param HtmlForm
   */
  protected function init( HtmlForm $form )
  {
    parent::init( $form );

    if( is_null($this->map) )
      $this->setMap( $this->name );
    if( is_null($this->alert) )
      $this->setAlert( 'Error on field: $label' );
    if( is_null($this->id) )
      $this->setID( substr(md5($this->name),0,4) );
    $this->setRequired( $this->required );
  }

  // }}}
  // {{{ stringifize()

  /**
   * Convertir tout tableau en cha�ne de caract�re en prenant le premier element uniquement.
   *
   * Permet de contrequarer les envoies de param�tres en utilisant la synataxe &var[]=value.
   *
   * @return string|null
   */
  static protected function stringifize( $value )
  {
    if( is_array($value) and $value )
      return self::stringifize( array_shift($value) );
    elseif( is_string($value) )
      return $value;
    else
      return null;
  }

  // }}}
  // {{{ arrayifize()

  /**
   * Convertit toute entr&eacute;e en tableau &agrave; 1 dimension.
   *
   * @return array
   */
  static protected function arrayifize( $value )
  {
    if( is_array($value) )
    {
      foreach( $value as $k => $v )
        $value[$k] = self::stringifize($v);
      return $value;
    }
    else
      return array(self::stringifize($value));
  }

  // }}}
  // {{{ currentElement()

  /**
   * @ignore
   */
  public function currentElement()
  {
    return $this;
  }

  // }}}
  // {{{ nextElement()

  /**
   * @ignore
   */
  public function nextElement()
  {
    $this->_valid = false;
  }

  // }}}
  // {{{ elementKey()

  /**
   * @ignore
   */
  public function elementKey()
  {
    return $this->__get( 'name' );
  }

  // }}}
  // {{{ rewindElements()

  /**
   * @ignore
   */
  public function rewindElements()
  {
    $this->_valid = true;
  }

  // }}}
  // {{{ elementIsValid()

  /**
   * @ignore
   */
  public function elementIsValid()
  {
    return $this->_valid;
  }

  // }}}
  // {{{ getHtml()

  public function getHtml()
  {
    if( $this->_form->send )
      return htmlspecialchars(self::autoslashes($this->value));
    elseif( $this->default )
      return htmlspecialchars($this->default);
    else
      return null;
  }

  // }}}
  // {{{ $_hidden

  protected $_hidden = false;

  // }}}
  // {{{ setHidden()

  protected function setHidden( $hidden )
  {
    if( ! is_bool($hidden) ) throw new InvalidArgumentException(sprintf('%s::%s() need a boolean for his 1st parameter',__CLASS__,__FUNCTION__));

    $this->_hidden = $hidden;
  }

  // }}}
}

// }}}
// {{{ HtmlFormI18n

/**
 * Classe pour l'internationalisation des elements.
 */
abstract class HtmlFormI18n implements ArrayAccess, Iterator
{
  // {{{ $strings

  /**
   * Un tableau des cha&icirc;nes traduite
   */
  protected $strings = array();

  // }}}
  // {{{ $element

  /**
   * Une reference vers l'element traduit.
   */
  protected $element = null;

  // }}}
  // {{{ __construct()

  final public function __construct( HtmlFormElement $element )
  {
    $this->element = $element;
  }

  // }}}
  // {{{ __get()

  /**
   * Surcharge les proprietes.
   *
   * @param string Le nom de la propriete.
   * @return mixed
   */
  public function __get( $property )
  {
    if( ! is_string($property) ) throw new InvalidArgumentException;

    if( $property === 'class' )
      return get_class($this);

    if( ! method_exists( $this, 'get'.ucfirst($property) ) )
    {
      if( ! property_exists( $this, '_'.$property ) )
        throw new BadPropertyException( sprintf('The property "%s::%s" is undefined',get_class($this),$property) );
      else
      {
        $property = '_'.$property;
        return $this->$property;
      }
    }
    else
      return call_user_func( array($this,'get'.ucfirst($property)) );
  }

  // }}}
  // {{{ __set()

  /**
   * Surharge les propr&eacute;t&eacute;s.
   *
   * Permet de modifier les propri&eacute;t&eacute;s.
   *
   * @param string Le nom de la propri&eacute;t&eacute;.
   * @param mixed La nouvelle valeur.
   */
  public function __set( $property, $value )
  {
    if( ! is_string($property) ) throw new InvalidArgumentException;

    if( ! is_callable( array($this,'set'.ucfirst($property)) ) ) throw new BadPropertyException( sprintf('The property "%s::%s" is undefined',get_class($this),$property) );

    call_user_func( array($this,'set'.ucfirst($property)), $value );
  }

  // }}}
  // {{{ __isset()

  /**
   * Surcharge les propriétés.
   *
   * Permet d'utiliser la structure isset() sur les propriétés.
   *
   * @param string Le nom de la propriété.
   * @param boolean
   */
  public function __isset( $property )
  {
    try
    {
      $this->$property;
    }
    catch( BadPropertyException $e )
    {
      return false;
    }
    return true;
  }

  // }}}
  // {{{ current()

  /**
   * @ignore
   */
  public function current()
  {
    return current($this->strings);
  }

  // }}}
  // {{{ next()

  /**
   * @ignore
   */
  public function next()
  {
    return next($this->strings);
  }

  // }}}
  // {{{ key()

  /**
   * @ignore
   */
  public function key()
  {
    return key($this->strings);
  }

  // }}}
  // {{{ rewind()

  /**
   * @ignore
   */
  public function rewind()
  {
    return reset($this->strings);
  }

  // }}}
  // {{{ valid()

  /**
   * @ignore
   */
  public function valid()
  {
    return (bool)current($this->strings);
  }

  // }}}
  // {{{ offsetExists()

  /**
   * @ignore
   */
  public function offsetExists( $offset )
  {
    if( ! is_string($offset) ) throw new InvalidArgumentException;

    return isset($this->strings[$offset]);
  }

  // }}}
  // {{{ offsetGet()

  /**
   * @ignore
   */
  public function offsetGet( $offset )
  {
    if( ! is_string($offset) ) throw new InvalidArgumentException;

    if( ! isset($this->strings[$offset]) ) throw new OutOfBoundsException(sprintf('Try to acces to an undefined index: %.',$offset));

    return $this->strings[$offset];
  }

  // }}}
  // {{{ offsetSet()

  /**
   * @ignore
   */
  public function offsetSet( $offset, $value )
  {
    if( ! is_string($offset) ) throw new InvalidArgumentException;
    if( ! $value instanceof HtmlFormElement ) throw new InvalidArgumentException;

    $this->strings[$offset] = $value;
  }

  // }}}
  // {{{ offsetUnset()

  /**
   * @ignore
   */
  public function offsetUnset( $offset )
  {
    if( ! is_string($offset) ) throw new InvalidArgumentException;

    if( ! isset($this->strings[$offset]) ) throw new OutOfBoundsException(sprintf('Try to acces to an undefined index: %.',$offset));

    unset( $this->strings[$offset] );
  }

  // }}}
}

// }}}
// {{{ HtmlFormValuesContainer

/**
 * Classe abstraite de champ contenant des valeurs predefinies.
 */
abstract class HtmlFormValuesContainer extends HtmlFormElement implements ArrayAccess, Iterator
{
  // {{{ $_values

  /**
   * La listes de valeurs possibles.
   *
   * @var array
   */
  protected $_values = array();

  // }}}
  // {{{ setValues()

  /**
   * Modifie la liste des valeurs.
   *
   * @param array La liste des valeurs
   * @return HtmlFormElement
   */
  public function setValues( $values )
  {
    if( ! is_array($values) )
    {
      if( ! $this->_values )
        $this->_values[1] = $values;
      else
        $this->_values[] = $values;

      $args = func_get_args();
      if( count($args) > 1 )
      {
        array_shift($args);
        call_user_func_array( array($this,__FUNCTION__), $args );
      }
      return $this;
    }
    else
    {
      $this->_values = $values;

      return $this;
    }
  }

  // }}}
  // {{{ current()

  /**
   * @ignore
   */
  public function current()
  {
    if( $this->_i18n instanceof Iterator )
      return $this->_i18n->current();
    elseif( is_array($this->_i18n) )
      return current($this->_i18n);
    else
      return current($this->_values);
  }

  // }}}
  // {{{ next()

  /**
   * @ignore
   */
  public function next()
  {
    if( $this->_i18n instanceof Iterator )
      $this->_i18n->next();
    elseif( is_array($this->_i18n) )
      next($this->_i18n);
    else
      next($this->_values);
  }

  // }}}
  // {{{ key()

  /**
   * @ignore
   */
  public function key()
  {
    if( $this->_i18n instanceof Iterator )
      return $this->_i18n->key();
    elseif( is_array($this->_i18n) )
      return key($this->_i18n);
    else
    return key($this->_values);
  }

  // }}}
  // {{{ rewind()

  /**
   * @ignore
   */
  public function rewind()
  {
    if( $this->_i18n instanceof Iterator )
      return $this->_i18n->rewind();
    elseif( is_array($this->_i18n) )
      return reset($this->_i18n);
    else
      return reset($this->_values);
  }

  // }}}
  // {{{ valid()

  /**
   * @ignore
   */
  public function valid()
  {
    if( $this->_i18n instanceof Iterator )
      return $this->_i18n->valid();
    elseif( is_array($this->_i18n) )
      return (boolean)current($this->_i18n);
    else
      return (boolean)current($this->_values);
  }

  // }}}
  // {{{ offsetExists()

  /**
   * @ignore
   */
  public function offsetExists( $offset )
  {
    if( ! is_string($offset) and ! is_integer($offset) ) throw new InvalidArgumentException;

    if( $this->_i18n instanceof Iterator or is_array($this->_i18n) )
      return isset($this->_i18n[$offset]);
    else
      return isset($this->_values[$offset]);
  }

  // }}}
  // {{{ offsetGet()

  /**
   * @ignore
   */
  public function offsetGet( $offset )
  {
    if( ! is_string($offset) and ! is_integer($offset) ) throw new InvalidArgumentException;

    if( $this->_i18n instanceof Iterator or is_array($this->_i18n) )
    {
      if( ! isset($this->_i18n[$offset]) ) throw new OutOfBoundsException(sprintf('Try to acces to an undefined index: %.',$offset));
      return isset($this->_i18n[$offset]);
    }
    else
    {
      if( ! isset($this->_values[$offset]) ) throw new OutOfBoundsException(sprintf('Try to acces to an undefined index: %.',$offset));
      return $this->_values[$offset];
    }
  }

  // }}}
  // {{{ offsetSet()

  /**
   * @ignore
   */
  public function offsetSet( $offset, $value )
  {
    if( ! is_string($offset) and ! is_integer($offset) ) throw new InvalidArgumentException;
    if( ! is_string($value) ) throw new InvalidArgumentException(sprintf('%s::%s() need a string for his 2nd parameter',__CLASS__,__FUNCTION__));

    if( $this->_i18n instanceof Iterator or is_array($this->_i18n) )
      $this->_i18n[$offset] = $value;
    else
      $this->_values[$offset] = $value;
  }

  // }}}
  // {{{ offsetUnset()

  /**
   * @ignore
   */
  public function offsetUnset( $offset )
  {
    if( ! is_string($offset) and ! is_integer($offset) ) throw new InvalidArgumentException;

    if( $this->_i18n instanceof Iterator or is_array($this->_i18n) )
    {
      if( ! isset($this->_i18n[$offset]) ) throw new OutOfBoundsException(sprintf('Try to acces to an undefined index: %.',$offset));
      unset( $this->_i18n[$offset] );
    }
    else
    {
      if( ! isset($this->_values[$offset]) ) throw new OutOfBoundsException(sprintf('Try to acces to an undefined index: %.',$offset));
      unset( $this->_values[$offset] );
    }
  }

  // }}}
  // {{{ init()

  /**
   * Initialise un element de type text.
   *
   * V&eacute;rifie et corrige le faite que la valeur de l'element ne peut pas &ecirc;tre un tableau.
   */
  protected function init( HtmlForm $form )
  {
    parent::init( $form );

    if( is_array($this->value) and $this->value )
      $this->value = array_shift($this->value);
    elseif( is_array($this->value) )
      $this->value = null;

    // fixme: utiliser setCheck()
    array_push( $this->_check, array(__CLASS__,'checkValues') );
  }

  // }}}
  // {{{ checkValues()

  /**
   * Fonction de callback.
   *
   * V&eacute;rifie si la valeur envoy&eacute; par client fait parti de la listes des valeurs possibles.
   *
   * @param HtmlFormElement
   * @param HtmlForm
   * @return boolean
   */
  static protected function checkValues( HtmlFormElement $element, HtmlForm $form )
  {
    if( ! is_array($element->values) ) throw new UnexpectedValueException(sprintf('%s::%s must be an array.',get_class($element),'$values'));

    if( is_array( $value = $element->value ) )
    {
      $error = false;
      foreach( $value as $v )
        $error |= ! isset($element[$v]);
      return $error;
    }
    elseif( is_string($value) )
      return ! isset($element[$value]);
    else
      return true;
  }

  // }}}
}

// }}}
// {{{ HtmlFormText

/**
 * Classe d'un champ de type texte.
 */
class HtmlFormText extends HtmlFormArea
{
  // {{{ $_password

  protected $_password = false;

  // }}}
  // {{{ setPassword()

  protected function setPassword( $password )
  {
    if( ! is_bool($password) ) throw new InvalidArgumentException(sprintf('%s::%s() need a boolean for his 1st parameter',__CLASS__,__FUNCTION__));

    $this->_password = $password;
    if( $password )
      $this->_hidden = false;
  }

  // }}}
  // {{{ $_max_length

  protected $_max_length = null;

  // }}}
  // {{{ getValue()

  protected function getValue()
  {
    return self::stringifize( parent::getValue() );
  }

  // }}}
  // {{{ setMaxLength()

  public function setMaxLength( $length )
  {
    if( ! is_integer($length) and ! is_string($length) and ! is_null($length) ) throw new InvalidArgumentException;

    $this->_max_length = (integer)$length;

    return $this;
  }

  // }}}
  // {{{ setHidden()

  protected function setHidden( $hidden )
  {
    if( ! is_bool($hidden) ) throw new InvalidArgumentException(sprintf('%s::%s() need a boolean for his 1st parameter',__CLASS__,__FUNCTION__));

    $this->_hidden = $hidden;
    if( $hidden )
      $this->_password = false;
  }

  // }}}
}

// }}}
// {{{ HtmlFormArea

class HtmlFormArea extends HtmlFormElement
{
  // {{{ init()

  /**
   * Initialise un element de type text.
   *
   * V&eacute;rifie et corrige le faite que la valeur de l'element ne peut pas &ecirc;tre un tableau.
   *
   * @param HtmlForm
   */
  protected function init( HtmlForm $form )
  {
    parent::init( $form );

    if( is_array($this->value) and $this->value )
      $this->value = array_shift($this->value);
    elseif( is_array($this->value) )
      $this->value = null;
  }

  // }}}
}

// }}}
// {{{ HtmlFormDropdown

/**
 * Classe d'un champ de type select.
 */
class HtmlFormDropdown extends HtmlFormValuesContainer
{
  // {{{ $_choice

  /**
   * Le premier element de la liste.
   *
   * @var string|false
   */
  protected $_choice = '-- select --';

  // }}}
  // {{{ setChoice()

  /**
   * Modifie la premi&egrave;re valeur.
   *
   * @param string
   * @return HtmlFormSelect
   */
  public function setChoice( $choice )
  {
    if( ! is_string($choice) ) throw new InvalidArgumentException;

    $this->_choice = $choice;

    return $this;
  }

  // }}}
  // {{{ getValue()

  protected function getValue()
  {
    return self::stringifize( parent::getValue() );
  }

  // }}}
  // {{{ setI18n()

  /**
   * Modifie les données de traduction.
   *
   * @param HtmlFormElement
   * @param Iterator|array|string
   * @return HtmlFormElement
   */
  public function setI18n( $i18n )
  {
    parent::setI18n( $i18n );

    if( isset($this->_i18n->choice) )
      $this->choice = $this->_i18n->choice;

    if( isset($this->_i18n->label) )
      $this->label = $this->_i18n->label;

    return $this;
  }

  // }}}
}

// }}}
// {{{ HtmlFormButton

/**
 * Classe de champ de type button.
 */
class HtmlFormButton extends HtmlFormElement
{
  // {{{ getValue()

  protected function getValue()
  {
    return self::stringifize( parent::getValue() );
  }

  // }}}
}

// }}}
// {{{ HtmlFormRadio

/**
 * Classe de champ de type radio.
 */
class HtmlFormRadio extends HtmlFormValuesContainer
{
  // {{{ getValue()

  protected function getValue()
  {
    return self::stringifize( parent::getValue() );
  }

  // }}}
}

// }}}
// {{{ HtmlFormRadios

/**
 * Alias pour HtmlFormRadio.
 */
class HtmlFormRadios extends HtmlFormRadio
{
}

// }}}
// {{{ HtmlFormCheckbox

/**
 * Classe de champ de type checkbox.
 */
class HtmlFormCheckbox extends HtmlFormElement
{
  // {{{ getValue()

  protected function getValue()
  {
    return self::stringifize( parent::getValue() );
  }

  // }}}
}

// }}}
// {{{ HtmlFormCheckboxs

/**
 * Classe de groupe de champ de type checkbox.
 */
class HtmlFormCheckboxs extends HtmlFormValuesContainer
{
  // {{{ getValue()

  protected function getValue()
  {
    return self::arrayifize( parent::getValue() );
  }

  // }}}
}

// }}}
// {{{ HtmlFormGroupedElements

/**
 * Champ compose
 */
abstract class HtmlFormGroupedElements extends HtmlFormElement
{
  // {{{ $_components

  protected $_components = array();

  // }}}
  // {{{ $_current

  protected $_current = null;

  // }}}
  // {{{ currentElement()

  /**
   * @ignore
   */
  public function currentElement()
  {
    return current($this->_components);
  }

  // }}}
  // {{{ elementKey()

  /**
   * @ignore
   */
  public function elementKey()
  {
    return key($this->_components);
  }

  // }}}
  // {{{ elementIsValid()

  /**
   * @ignore
   */
  public function elementIsValid()
  {
    return (boolean)current($this->_components);
  }

  // }}}
  // {{{ nextElement()

  /**
   * @ignore
   */
  public function nextElement()
  {
    next($this->_components);
  }

  // }}}
  // {{{ rewindElements()

  /**
   * @ignore
   */
  public function rewindElements()
  {
    reset($this->_components);
  }

  // }}}
  // {{{ isFirst()

  public function isFirst( HtmlFormCustomElement $element )
  {
    $components = $this->_components;
    return $element === array_shift($components);
  }

  // }}}
  // {{{ isLast()

  public function isLast( HtmlFormCustomElement $element )
  {
    $components = $this->_components;
    return $element === array_pop($components);
  }

  // }}}
}

// }}}
// {{{ HtmlFormInteger

/**
 * Classe de champ de type text - nombre
 * @todo tester le dépassement des nombres
 */
class HtmlFormInteger extends HtmlFormText
{
  // {{{ $min

  protected $_min = null;

  // }}}
  // {{{ $max

  protected $_max = null;

  // }}}
  // {{{ setBound

  /**
   * Change les valeurs maximun et minimun d'une ann&eacute;e.
   *
   * @param string|integer|null la valeur minimum
   * @param string|integer|null la valeur maximum
   * @return HtmlFormYear
   */
  public function setBound( $min = null, $max = null )
  {
    if( ! is_scalar($min) and ! is_null($min) ) throw new InvalidArgumentException(sprintf('%s::%s need a string or an integer for his 1st parameter',__CLASS__,__FUNCTION__));
    if( ! is_scalar($max) and ! is_null($max) ) throw new InvalidArgumentException(sprintf('%s::%s need a string or an integer for his 2nd parameter',__CLASS__,__FUNCTION__));

    if( is_scalar($min) )
      $this->_min = (integer)$min;
    if( is_scalar($max) )
      $this->_max = (integer)$max;

    return $this;
  }

  // }}}
  // {{{ setBounds

  /**
   * Alias pour setBound()
   *
   * @param string|integer|null la valeur minimum
   * @param string|integer|null la valeur maximum
   * @return HtmlFormYear
   */
  public function setBounds( $min = null, $max = null )
  {
    if( ! is_scalar($min) ) throw new InvalidArgumentException(sprintf('%s::%s need a string or an integer for his 1st parameter',__CLASS__,__FUNCTION__));
    if( ! is_scalar($max) ) throw new InvalidArgumentException(sprintf('%s::%s need a string or an integer for his 2nd parameter',__CLASS__,__FUNCTION__));

    return $this->setBound( $min, $max );
  }

  // }}}
  // {{{ checkInteger()

  /**
   * Fonction de callback
   *
   * Verifie si la valeur envoye par le client est un entier.
   */
  static protected function checkInteger( HtmlFormElement $element, HtmlForm $form )
  {
    return (string)(integer)($element->value) != (string)$element->value;
  }

  // }}}
  // {{{ checkBounds()

  /**
   * Fonction de callback.
   *
   * Verifie si la valeur envoyee par client est contenant entre les bornes.
   *
   * @param HtmlFormElement
   * @param HtmlForm
   * @return boolean
   */
  static protected function checkBounds( HtmlFormElement $element, HtmlForm $form )
  {
    if( ! $element instanceof HtmlFormInteger ) throw new InvalidArgumentException(sprintf('%s::%s() need a HtmlFormInteger object for his 1st parameter',__CLASS__,__FUNCTION__));

    if( is_integer($element->min) or is_integer($element->max) )
    {
      if( is_integer($element->min) and $element->value < $element->min )
        return 'out_of_bound';
      if( is_integer($element->max) and $element->value > $element->max )
        return 'out_of_bound';
    }
    return false;
  }

  // }}}
  // {{{ init()

  protected function init( HtmlForm $form )
  {
    parent::init( $form );

    $this->setCheck(array($this->class,'checkInteger'));
    $this->setCheck(array($this->class,'checkBounds'));

    $this->setAlert('Out of bound for field: $label','out_of_bound');
  }

  // }}}
  // {{{ getValue()

  protected function getValue()
  {
    $value = trim(parent::getValue());

    if( preg_match('/^[\d]+$/',$value) )
      return  (string)(integer)$value;
    else
      return $value;
  }

  // }}}
}

// }}}
// {{{ HtmlFormDay

/**
 * Classe de champ de type text - jour
 */
class HtmlFormDay extends HtmlFormInteger
{
  // {{{ $_name

  protected $_name = 'day';

  // }}}
  // {{{ $_month

  protected $_month = null;

  // }}}
  // {{{ $_year

  protected $_year = null;

  // }}}
  // {{{ setOfMonth()

  public function setOfMonth( $month = 'month' )
  {
    if( ! is_string($month) and ! is_integer($month) and ( isset($this->form[(string)$month]) and ! $this->form[(string)$month] instanceof HtmlFormMonth ) ) throw new InvalidArgumentException(sprintf('%s::%s() need a string, integer or HtmlFormMonth object for his 1st parameter',__CLASS__,__FUNCTION__));

    if( $month instanceof HtmlFormMonth )
      $this->_month = $month;
    elseif( is_string($month) and isset($this->form[$month]) )
      $this->_month = $this->form[$month];
    else
      $this->_month = HtmlFormMonth::parseMonth($month);
  }

  // }}}
  // {{{ setOfYear()

  public function setOfYear( $year = 'year' )
  {
    if( ! is_string($year) and ! is_integer($year) and ( isset($this->form[(string)$year]) and ! $this->form[(string)$year] instanceof HtmlFormYear  and ! $form[(string)$year] instanceof HtmlFormYearDown ) ) throw new InvalidArgumentException(sprintf('%s::%s() need a string, integer, HtmlFormYear object or HtmlFormYearDown object for his 1st parameter',__CLASS__,__FUNCTION__));

    if( $year instanceof HtmlFormYear or $year instanceof HtmlFormYearDown )
      $this->_year = $year;
    elseif( is_string($year) and isset($this->form[$year]) )
      $this->_year = $this->form[$year];
    else
      $this->_year = HtmlFormYear::parseYear($year);
  }

  // }}}
  // {{{ setBound()

  /**
   * Change les valeurs maximun et minimun d'un jour.
   *
   * @param string|null la valeur minimum
   * @param string|null la valeur maximum
   * @return HtmlFormDay
   */
  public function setBound( $min = null, $max = null )
  {
    if( ! is_string($min) and ! is_null($min) ) throw new InvalidArgumentException(sprintf('%s::%s need a string for his 1st parameter',__CLASS__,__FUNCTION__));
    if( ! is_string($max) and ! is_null($max) ) throw new InvalidArgumentException(sprintf('%s::%s need a string for his 2nd parameter',__CLASS__,__FUNCTION__));

    $this->_min = max( 1, min( 31, (integer)self::parseDay($min) ) );
    $this->_max = max( 1, min( 31, (integer)self::parseDay($max) ) );

    return $this;
  }

  // }}}
  // {{{ checkBounds()

  /**
   * Fonction de callback.
   *
   * Verifie si la valeur envoyee par le client est comprise dans les bornes.
   *
   * Retourne "true" si la valeur n'est pas valide, sinon "false".
   *
   * @param HtmlFormElement
   * @param HtmlForm
   * @return boolean
   */
  static protected function checkBounds( HtmlFormElement $element, HtmlForm $form )
  {
    if( ! $element instanceof HtmlFormInteger ) throw new InvalidArgumentException(sprintf('%s::%s() need a HtmlFormInteger object for his 1st parameter',__CLASS__,__FUNCTION__));

    if( is_integer($element->min) or is_integer($element->max) )
    {
      $day = self::parseDay($element->value);
      if( is_integer($element->min) and $day < $element->min )
        return 'out_of_bound';
      if( is_integer($element->max) and $day > $element->max )
        return 'out_of_bound';
      elseif( isset($element->month) and isset($element->year) and version_compare(phpversion(), "5.2.0", ">=")
        and $day > (integer)date('t', strtotime(sprintf('%s-%s-1',(string)$element->year,(string)$element->month))) )
          return 'out_of_bound';
      elseif( isset($element->month) and isset($element->year) )
      {
        if( $element->month instanceof HtmlFormMonth )
          $month = $element->month->__toString();
        else
          $month = $element->month;
        if( $element->year instanceof HtmlFormYear or $element->year instanceof HtmlFormYearDown )
          $year = $element->year->__toString();
        else
          $year = $element->year;
        if( $day > (integer)date('t', strtotime(sprintf('%s-%s-1',$year,$month))) )
          return 'out_of_bound';
      }

    }
    return false;
  }

  // }}}
  // {{{ parseDay()

  /**
   * Analyse une chaine et retourne un jour.
   *
   * @return string|integer|null
   * @return integer|null
   */
  static public function parseDay( $value )
  {
    $value = ltrim(trim($value), '0');
    if( (string)(integer)$value === (string)$value )
      return (integer)$value;
    elseif( ($time = strtotime($value)) !== false and $time !== -1 )
      return (integer)date('j', $time);
    elseif( (integer)$value === 0 )
      return 0;
    else
      return null;
  }

  // }}}
  // {{{ setDefault()

  /**
   * Modifie la valeur par default du champ.
   *
   * @param scalar|array
   * @return HtmlFormDay
   */
  public function setDefault( $default )
  {
    if( ! is_scalar($default) and ! is_array($default) ) throw new InvalidArgumentException(sprintf('%s::%s() need a scalar or an array for his 1st parameter',__CLASS__,__FUNCTION__));

    $this->_default = self::parseDay($default);

    return $this;
  }

  // }}}
  // {{{ transformDay()

  static protected function transformDay( HtmlFormElement $element, HtmlForm $form )
  {
    $element->value = (string)self::parseDay($element->value);

    return false;
  }

  // }}}
  // {{{ init()

  protected function init( HtmlForm $form )
  {
    parent::init( $form );

    $this->_min = 1;

    $this->setCheck( array($this->class,'transformDay'), true );

    $this->setCheck( array('HtmlFormMonth','checkDayOfMonth') );
    $this->setAlert( 'Month $html did\'nt have $day days', 'bad_month_for_this_day' );
  }

  // }}}
  // {{{ __get()

  // fixme: remplacer par getTime
  public function __getttttttttttttttttttttttttttt( $property )
  {
    if( ! is_string($property) ) throw new InvalidArgumentException(sprintf('%s::%s() need a string for his 1st parametre',__CLASS__,__FUNCTION__));

    if( $property === 'time' )
    {
      if( version_compare(phpversion(), "5.2.0", ">=") )
        return strtotime(sprintf('%s-%s-%s', (string)$this->year, (string)$this->month, (string)$this));
      else
      {
        if( $this->_year instanceof HtmlFormYear or $this->_year instanceof HtmlFormYearDown )
          $year = $this->_year->value;
        else
          $year = $this->_year;
        if( $this->_month instanceof HtmlFormMonth )
          $month = $this->_month->value;
        else
          $month = $this->_month;
        $day = $this->value;
        return strtotime(sprintf('%s-%s-%s', $year, $month, $day));
      }
    }

    return parent::__get( $property );
  }

  // }}}
  // {{{ hie()

  /**
   * Instancie un element de type jour.
   *
   * @param HtmlForm L'instance du formulaire
   * @param string Le nom de la classe a instancier
   * @param string|null Le nom du champ
   * @param string|null Le nom d'un l'element month precedament ajouter
   * @param string|null Le nom d'un l'element year precedament ajouter
   * @return HtmlFormElement
   */
  static public function hie( HtmlForm $form, $class_name )
  {
    if( func_num_args() > 2 ) $name = func_get_arg(2); else $name = null;

    if( ! is_string($name) and ! is_null($name) ) throw new InvalidArgumentException(sprintf('%::%s need a string for his 3rd parameter.',__CLASS__,__FUNCTION__));

    $element = parent::hie( $form, $class_name, $name );

    if( func_num_args() > 3 ) $month = func_get_arg(3); else $month = null;

    if( ! is_string($month) and ( isset($form[(string)$month]) and ! $form[(string)$month] instanceof HtmlFormMonth ) and ! is_null($month) and ! is_integer($month) ) throw new InvalidArgumentException(sprintf('%::%s() need the name of an month element already added to the form or an integer or a string for his 3rd parameter.',__CLASS__,__FUNCTION__));

    if( is_string($month) and isset($form[(string)$month]) )
      $element->setOfMonth($form[$month]);
    elseif( is_string($month) or is_integer($month) )
      $element->setOfMonth($month);

    if( func_num_args() > 4 ) $year = func_get_arg(4); else $year = null;

    if( ! is_string($year) and ( isset($form[(string)$year]) and ! $form[(string)$year] instanceof HtmlFormYear and ! $form[(string)$year] instanceof HtmlFormYearDown ) and ! is_null($year) and ! is_integer($year) ) throw new InvalidArgumentException(sprintf('%::%s() need the name of an year element already added to the form or an integer or a string for his 3th parameter.',__CLASS__,__FUNCTION__));

    if( is_string($year) and isset($form[(string)$month]) )
      $element->setOfYear($form[$year]);
    elseif( is_string($year) or is_integer($year) )
      $element->setOfYear($year);

    return $element;
  }

  // }}}
}

// }}}
// {{{ HtmlFormYear

/**
 * Classe de champ de type text - annee
 */
class HtmlFormYear extends HtmlFormInteger
{
  // {{{ $_name

  protected $_name = 'year';

  // }}}
  // {{{ setBound

  /**
   * Change les valeurs maximun et minimun d'une annee.
   *
   * @param string|null la valeur minimum
   * @param string|null la valeur maximum
   * @return HtmlFormYear
   */
  public function setBound( $min = null, $max = null )
  {
    if( ! is_string($min) and ! is_null($min) ) throw new InvalidArgumentException(sprintf('%s::%s need a string for his 1st parameter',__CLASS__,__FUNCTION__));
    if( ! is_string($max) and ! is_null($max) ) throw new InvalidArgumentException(sprintf('%s::%s need a string for his 2nd parameter',__CLASS__,__FUNCTION__));

    $this->_min = self::parseYear($min);
    $this->_max = self::parseYear($max);

    return $this;
  }

  // }}}
  // {{{ $_month

  protected $_month = null;

  // }}}
  // {{{ $_day

  protected $_day = null;

  // }}}
  // {{{ checkBounds()

  /**
   * Fonction de callback.
   *
   * Verifie si la valeur envoyee par le client est comprise dans les bornes.
   *
   * @param HtmlFormElement
   * @param HtmlForm
   * @return boolean
   */
  static protected function checkBounds( HtmlFormElement $element, HtmlForm $form )
  {
    if( ! $element instanceof HtmlFormInteger ) throw new InvalidArgumentException(sprintf('%s::%s() need a HtmlFormInteger object for his 1st parameter',__CLASS__,__FUNCTION__));

    if( is_integer($element->min) or is_integer($element->max) )
    {
      $year = self::parseYear($element->value);
      if( is_integer($element->min) and $year < $element->min )
        return true;
      if( is_integer($element->max) and $year > $element->max )
        return true;
    }
    return false;
  }

  // }}}
  // {{{ parseYear()

  /**
   * Analyse une chaine et retourne une annee.
   *
   * @return string|integer|null
   * @return integer|null
   */
  static public function parseYear( $value )
  {
    $value = trim($value);
    if( (string)(integer)$value === (string)$value )
      return (integer)$value;
    elseif( ($time = strtotime($value)) !== false and $time !== -1 )
      return (integer)date('Y', $time);
    else
      return null;
  }

  // }}}
  // {{{ setDefault()

  /**
   * Modifie la valeur par default du champ.
   *
   * @param scalar|array
   * @return HtmlFormYear
   */
  public function setDefault( $default )
  {
    if( ! is_scalar($default) and ! is_array($default) ) throw new InvalidArgumentException(sprintf('%s::%s() need a scalar or an array for his 1st parameter',__CLASS__,__FUNCTION__));

    $this->_default = self::parseYear($default);

    return $this;
  }

  // }}}
  // {{{ getTime()

  protected function getTime()
  {
    if( version_compare(phpversion(), "5.2.0", ">=") )
      return strtotime(sprintf('%s-%s-%s', (string)$this, (string)$this->month, (string)$this->day));
    else
    {
      $year = $this->value;
      if( $this->_month instanceof HtmlFormMonth )
        $month = $this->_month->value;
      else
        $month = $this->_month;
      if( $this->_day instanceof HtmlFormDay )
        $month = $this->_day->value;
      else
        $month = $this->_day;
      return strtotime(sprintf('%s-%s-%s', $year, $month, $day));
    }
  }

  // }}}
  // {{{ init()

  protected function init( HtmlForm $form )
  {
    parent::init( $form );

    $this->setCheck( array($this->class,'transformYear'), true );

    $this->setCheck( array('HtmlFormMonth','checkDayOfMonth') );
    $this->setAlert( 'Month $month did\'nt have $day days', 'bad_month_for_this_day' );
  }

  // }}}}
  // {{{ transformYear()

  static protected function transformYear( HtmlFormElement $element, HtmlForm $form )
  {
    $element->value = (string)self::parseYear($element->value);

    return false;
  }

  // }}}
  // {{{ setOfMonth()

  public function setOfMonth( $month = 'month' )
  {
    if( ! is_string($month) and ! is_integer($month) and ( isset($this->form[(string)$month]) and ! $this->form[(string)$month] instanceof HtmlFormMonth ) ) throw new InvalidArgumentException(sprintf('%s::%s() need a string, integer or HtmlFormMonth object for his 1st parameter',__CLASS__,__FUNCTION__));

    if( $month instanceof HtmlFormMonth )
      $this->_month = $month;
    elseif( is_string($month) and isset($this->form[$month]) )
      $this->_month = $this->form[$month];
    else
      $this->_month = HtmlFormMonth::parseMonth($month);
  }

  // }}}
  // {{{ setOfDay()

  public function setOfDay( $day = 'day' )
  {
    if( ! is_string($day) and ! is_integer($day) and ( isset($form[(string)$day]) and ! $day instanceof HtmlFormDay ) ) throw new InvalidArgumentException(sprintf('%s::%s() need a string, integer or HtmlFormDay object for his 1st parameter',__CLASS__,__FUNCTION__));

    if( $day instanceof HtmlFormDay )
      $this->_day = $day;
    elseif( is_string($day) and isset($this->form[$day]) )
      $this->_day = $this->form[$day];
    else
      $this->_day = HtmlFormDay::parseDay($day);
  }

  // }}}
  // {{{ hie()

  /**
   * Instancie un element de type jour.
   *
   * @param HtmlForm L'instance du formulaire
   * @param string Le nom de la classe a instancier
   * @param string|null Le nom du champ
   * @param string|integer|null|HtmlFormMonth Le nom d'un l'element month precedament ajouter
   * @param string|integer|null|HtmlFormDay Le nom d'un l'element day precedament ajouter
   * @return HtmlFormElement
   */
  static public function hie( HtmlForm $form, $class_name )
  {
    if( func_num_args() > 2 ) $name = func_get_arg(2); else $name = null;

    if( ! is_string($name) and ! is_null($name) ) throw new InvalidArgumentException(sprintf('%::%s need a string for his 3rd parameter.',__CLASS__,__FUNCTION__));

    $element = parent::hie( $form, $class_name, $name );

    if( func_num_args() > 3 ) $month = func_get_arg(3); else $month = null;

    if( ! is_string($month) and ( isset($form[(string)$month]) and ! $form[(string)$month] instanceof HtmlFormMonth) and ! is_null($year) and ! is_integer($month) ) throw new InvalidArgumentException(sprintf('%::%s() need the name of an month element already added to the form or an integer or a string for his 3rd parameter.',__CLASS__,__FUNCTION__));

    if( is_string($month) and isset($form[(string)$month]) )
      $element->setOfMonth($form[$month]);
    elseif( is_string($month) or is_integer($month) )
      $element->setOfMonth($month);

    if( func_num_args() > 4 ) $day = func_get_arg(4); else $day = null;

    if( ! is_string($day) and ( isset($form[(string)$day]) and ! $form[(string)$day] instanceof HtmlFormDay ) and ! is_null($day) and ! is_integer($day) ) throw new InvalidArgumentException(sprintf('%::%s() need the name of an day element already added to the form or an integer or a string for his 4th parameter.',__CLASS__,__FUNCTION__));

    if( is_string($day) and isset($form[(string)$day]) )
      $element->setOfDay($form[$day]);
    elseif( is_string($day) or is_integer($day) )
      $element->setOfDay($day);

    return $element;
  }

  // }}}
}

// }}}
// {{{ HtmlFormYearDown

/**
 * Classe de champ de type select - annee
 */
class HtmlFormYearDown extends HtmlFormDropdown
{
  // {{{ $_values

  protected $_values = 0;

  // }}}
  // {{{ setValues()

  public function setValues( $values )
  {
  }

  // }}}
  // {{{ $_name

  protected $_name = 'year';

  // }}}
  // {{{ $htmlformyear

  protected $_htmlformyear = null;

  // }}}
  // {{{ init()

  protected function init( HtmlForm $form )
  {
    parent::init( $form );

    $this->_htmlformyear = new HtmlFormYear( $form );

    array_pop( $this->_check );
    array_push( $this->_check, array($this->htmlformyear->class,'checkBounds') );
  }

  // }}}
  // {{{ setBound

  /**
   * Change les valeurs maximun et minimun d'une ann&eacute;e.
   *
   * @param string|null la valeur minimum
   * @param string|null la valeur maximum
   * @return HtmlFormYear
   */
  public function setBound( $min = null, $max = null )
  {
    if( ! is_string($min) and ! is_null($min) ) throw new InvalidArgumentException(sprintf('%s::%s need a string for his 1st parameter',__CLASS__,__FUNCTION__));
    if( ! is_string($max) and ! is_null($max) ) throw new InvalidArgumentException(sprintf('%s::%s need a string for his 2nd parameter',__CLASS__,__FUNCTION__));

    //$this->values = $min;
    return $this->htmlformyear->setBound( $min, $max );
  }

  // }}}
  // {{{ setBounds

  /**
   * Alias pour setBound()
   *
   * @param string|null la valeur minimum
   * @param string|null la valeur maximum
   * @return HtmlFormYear
   */
  public function setBounds( $min = null, $max = null )
  {
    if( ! is_string($min) and ! is_null($min) ) throw new InvalidArgumentException(sprintf('%s::%s need a string for his 1st parameter',__CLASS__,__FUNCTION__));
    if( ! is_string($max) and ! is_null($max) ) throw new InvalidArgumentException(sprintf('%s::%s need a string for his 2nd parameter',__CLASS__,__FUNCTION__));

    return $this->htmlformyear->setBound( $min, $max );
  }

  // }}}
  // {{{ current()

  /**
   * @ignore
   */
  public function current()
  {
    return $this->_values;
  }

  // }}}
  // {{{ next()

  /**
   * @ignore
   */
  public function next()
  {
    $this->_values++;
  }

  // }}}
  // {{{ key()

  /**
   * @ignore
   */
  public function key()
  {
    return $this->_values;
  }

  // }}}
  // {{{ rewind()

  /**
   * @ignore
   */
  public function rewind()
  {
    return $this->_values = $this->htmlformyear->min;
  }

  // }}}
  // {{{ valid()

  /**
   * @ignore
   */
  public function valid()
  {
    return (boolean)($this->_values <= $this->htmlformyear->max);
  }

  // }}}
  // {{{ __get()

  public function __get( $property )
  {
    if( ! is_string($property) ) throw new InvalidArgumentException(sprintf('%s::%s() need a string for his 1st parametre',__CLASS__,__FUNCTION__));

    if( $property === 'min' )
      return $this->htmlformyear->min;
    elseif( $property === 'max' )
      return $this->htmlformyear->max;

    return parent::__get( $property );
  }

  // }}}
  // {{{ offsetExists()

  /**
   * @ignore
   */
  public function offsetExists( $offset )
  {
    if( ! is_string($offset) and ! is_integer($offset) ) throw new InvalidArgumentException;

    return ($offset >= $this->htmlformyear->min and $offset <= $this->htmlformyear->max);
  }

  // }}}
  // {{{ offsetGet()

  /**
   * @ignore
   */
  public function offsetGet( $offset )
  {
    if( ! is_string($offset) and ! is_integer($offset) ) throw new InvalidArgumentException;

    if( ! isset($this->values[$offset]) ) throw new OutOfBoundsException(sprintf('Try to acces to an undefined index: %.',$offset));

    return $offset;
  }

  // }}}
  // {{{ offsetSet()

  /**
   * @ignore
   */
  public function offsetSet( $offset, $value )
  {
    throw new BadMethodCallException;
  }

  // }}}
  // {{{ offsetUnset()

  /**
   * @ignore
   */
  public function offsetUnset( $offset )
  {
    throw new BadMethodCallException;
  }

  // }}}
  // {{{ setDefault()

  /**
   * Modifie la valeur par default du champ.
   *
   * @param scalar|array
   * @return HtmlFormYear
   */
  public function setDefault( $default )
  {
    if( ! is_scalar($default) and ! is_array($default) ) throw new InvalidArgumentException(sprintf('%s::%s() need a scalar or an array for his 1st parameter',__CLASS__,__FUNCTION__));

    $this->_default = HtmlFormYear::parseYear($default);

    return $this;
  }

    // }}}
}

// }}}
// {{{ HtmlFormSubscribe

/**
 * Classe de champ de type checkbox - subscribe
 */
class HtmlFormSubscribe extends HtmlFormCheckbox
{
  // {{{ $_name

  protected $_name = 'subscribe';

  // }}}
  // {{{ $_email

  /**
   * Une reference vers l'element email.
   *
   * @var HtmlFormEmail
   */
  protected $_email = null;

  // }}}
  // {{{ setOfEmail()

  /**
   * Modifie l'instance de l'&eacute;l&eacute;ment email.
   *
   * @param string|HtmlFormEmail
   * @return HtmlFormElement
   */
  public function setOfEmail( $email )
  {
    if( ! $email instanceof HtmlFormEmail and ! is_string($email) and ! ( isset($this->form[(string)$email]) and $this->form[(string)$email] instanceof HtmlFormEmail ) ) throw new InvalidArgumentException(sprintf('%s::%s() need a HtmlFormEmail object or the name of a email element for his 1st parameter.',__CLASS__,__FUNCTION__));

    if( $email instanceof HtmlFormEmail )
      $this->_email = $email;
    elseif( is_string($email) )
      $this->_email = $this->form[$email];

    return $this;
  }

  // }}}
  // {{{ hie()

  /**
   * Instancie un element
   *
   * @param HtmlForm L'instance du formulaire
   * @param string Le nom de la classe a instancier
   * @param string|null Le nom d'un l'element email precedament ajouter
   * @return HtmlFormElement
   */
  static public function hie( HtmlForm $form, $class_name )
  {
    $element = parent::hie( $form, $class_name );

    if( func_num_args() > 2 ) $email = func_get_arg(2); else $email = null;

    if( ! is_string($email) and ! ( isset($form[(string)$email]) and $form[(string)$email] instanceof HtmlFormEmail ) and ! is_null($email) ) throw new InvalidArgumentException(sprintf('%::%s() need the name of an email element already added to the form for his 3rd parameter.',__CLASS__,__FUNCTION__));

    if( is_string($email) )
      $element->setOfEmail($form[$email]);

    return $element;
  }

  // }}}
  // {{{ init()

  protected function init( HtmlForm $form)
  {
    parent::init( $form );

    // fixme: use setCheck()
    array_push( $this->_check, array(__CLASS__,'checkEmail') );

    if( $this->value === '' )
      $this->value = '0';
  }

  // }}}
  // {{{ checkEmail()

  /**
   * Fonction de callback.
   *
   * V&eacute;rifie si la l'adresse email &agrave; &eacute;t&eacute; renseign&eacute;
   *
   * @param HtmlFormElement
   * @param HtmlForm
   * @return boolean
   */
  static protected function checkEmail( HtmlFormElement $element, HtmlForm $form )
  {
    if( $element->email instanceof HtmlFormEmail and $element->value==='1' )
    {
        $element->email->required = $element->required;
        if( $element->email->ifError( $element->email->value, $form ) )
          return true;
    }

    return false;
  }


  // }}}
  // {{{ __get()

  public function __get( $property )
  {
    if( ! is_string($property) ) throw new InvalidArgumentException(sprintf('%s::%s() need a string for his 1st parametre',__CLASS__,__FUNCTION__));

    if( $property === 'error' )
      return false;
    elseif( $property === 'required' )
      return false;

    return parent::__get( $property );
  }

  // }}}
}

// }}}
// {{{ HtmlFormEmail

/**
 * Classe de champ de type texte - email.
 */
class HtmlFormEmail extends HtmlFormText
{
  // {{{ $name

  protected $_name = 'email';

  // }}}
  // {{{ $check

  protected $_check = array(
    '/^[a-z0-9._%-]+@[a-z0-9._%-]+\\.[a-z]{2,4}$/i'
    );

  // }}}
   // {{{ $_email_ref

  protected $_email_ref = null;

  // }}}
  // {{{ setOfEmail()

  public function setOfEmail( $email = 'email' )
  {
    if( ! is_string($email) and ! $email instanceof HtmlFormEmail ) throw new InvalidArgumentException(sprintf('%s::%s() need a string or HtmlFormEmail object for his 1st parameter',__CLASS__,__FUNCTION__));

    if( $email instanceof HtmlFormEmail )
      $this->_email_ref = $email;
    elseif( is_string($email) and isset($this->form[$email]) )
      $this->_email_ref = $this->form[$email];
    else
      $this->_email_ref = $email;

    return $this;
  }

  // }}}
  // {{{ init()

  protected function init( HtmlForm $form )
  {
    parent::init( $form );

    $this->setCheck( array($this->class,'checkEmailRef'));

    $this->setAlert( 'Emails don\'t match', 'emails_dont_match' );
  }

  // }}}
  // {{{ checkEmailRef()

  static protected function checkEmailRef( HtmlFormElement $element, HtmlForm $form )
  {
    if( is_string($element->_email_ref) and $element->value !== $element->_email_ref )
      return 'emails_dont_match';

    if( $element->_email_ref instanceof HtmlFormEmail and $element->value !== $element->_email_ref->value )
      return 'emails_dont_match';

    return false;
  }

  // }}}

}

// }}}
// {{{ HtmlFormEmails

class HtmlFormEmails extends HtmlFormGroupedElements
{
  // {{{ $_name

  protected $_name = 'email';

  // }}}
  // {{{ init()

  protected function init( HtmlForm $form )
  {
    $this->_components['email1'] = HtmlFormEmail::hie( $form, 'HtmlFormEmail', $this->name.'_email1' )->setOverride( $this );
    $this->_components['email2'] = HtmlFormEmail::hie( $form, 'HtmlFormEmail', $this->name.'_email2' )->setOfEmail( $this->_components['email1'] )->setOverride( $this );

    $this->_components['email1']->setOfEmail( $this->_components['email2'] );

    parent::init( $form );
  }

  // }}}
  // {{{ getOverridedLabel()

  public function getOverridedLabel()
  {
    return $this->label;
  }

  // }}}
  // {{{ getOverridedRequired()

  public function getOverridedRequired()
  {
    return $this->_required;
  }

  // }}}
  // {{{ getOverridedAlert()

  public function getOverridedAlert()
  {
    return $this->_alert;
  }

  // }}}
  // {{{ getOverridedError()

  public function getOverridedError()
  {
    if( $this->_components['email1']->_error )
      return $this->_components['email1']->_error;
    elseif( $this->_components['email2']->_error )
      return $this->_components['email2']->_error;
  }

  // }}}
  // {{{ getValue()

  public function getValue()
  {
    return $this->_components['email1']->value;
  }

  // }}}
  // {{{ getOverridedExport()

  protected function getOverridedExport()
  {
    return $this->_components['email1']->value;
  }

  // }}}
}

// }}}
// {{{ HtmlFormSubmit

/**
 * Classe de champ de type button - submit
 */
class HtmlFormSubmit extends HtmlFormButton
{
  // {{{ $_map

  protected $_map = false;

  // }}}
  // {{{ $_name

  protected $_name = 'submit';

  // }}}
  // {{{ $_required

  protected $_required = 'Form must be submited';

  // }}}
  // {{{ init()

  protected function init( HtmlForm $form )
  {
    parent::init( $form );

    $this->setName( $form->name );
  }

  // }}}
  // {{{ __get()

  public function __get( $property )
  {
    if( ! is_string($property) ) throw new InvalidArgumentException(sprintf('%s::%s() need a string for his 1st parametre',__CLASS__,__FUNCTION__));

    if( $property === 'value' )
      return self::getValue($this->name);

    return parent::__get( $property );
  }

  // }}}
}

// }}}
// {{{ HtmlFormCountry

/**
 * Classe de champ de type select - country.
 */
class HtmlFormCountry extends HtmlFormDropdown
{
  // {{{ $_name

  protected $_name = 'Country';

  // }}}
  // {{{ $_choice

  protected $_choice = '-- Select a country --';

  // }}}
  // {{{ init()

  protected function init( HtmlForm $form )
  {
    parent::init( $form );

    $this->i18n = new HtmlFormCountryEnglishI18n( $this );
  }

  // }}}
}

// }}}
// {{{ HtmlFormPostalCodeGroup

/**
 * Classe de champ de type select - postal code by groupe
 */
class HtmlFormPostalCodeGroup extends HtmlFormDropdown

{
  // {{{ $_name

  protected $_name = 'Postal code';

  // }}}
  // {{{ $_choice

  protected $_choice = '-- Select a poste code --';

  // }}}
  // {{{ init()

  protected function init( HtmlForm $form )
  {
    parent::init( $form );

    $this->i18n = new HtmlFormPostalCodeGroupFrenchI18n( $this );
  }

  // }}}
}

// }}}
// {{{ HtmlFormPassword

class HtmlFormPassword extends HtmlFormText
{
  // {{{ $_password_ref

  protected $_password_ref = null;

  // }}}
  // {{{ setOfPassword()

  public function setOfPassword( $password = 'password' )
  {
    if( ! is_string($password) and ! $password instanceof HtmlFormPassword ) throw new InvalidArgumentException(sprintf('%s::%s() need a string or HtmlFormPassword object for his 1st parameter',__CLASS__,__FUNCTION__));

    if( $password instanceof HtmlFormPassword )
      $this->_password_ref = $password;
    elseif( is_string($password) and isset($this->form[$password]) )
      $this->_password_ref = $this->form[$password];
    else
      $this->_password_ref = $password;

    return $this;
  }

  // }}}
  // {{{ init()

  protected function init( HtmlForm $form )
  {
    parent::init( $form );

    $this->_password = true;

    $this->setCheck( array($this->class,'checkPasswordRef'), true );
    $this->setAlert( 'Passwords dont match', 'passwords_dont_match' );
  }

  // }}}
  // {{{ checkPasswordRef()

  static protected function checkPasswordRef( HtmlFormElement $element, HtmlForm $form )
  {
    if( is_string($element->_password_ref) and $element->value !== $element->_password_ref )
      return 'passwords_dont_match';

    if( $element->_password_ref instanceof HtmlFormPassword and $element->value !== $element->_password_ref->value )
      return 'passwords_dont_match';

    return false;
  }

  // }}}
}

// }}}
// {{{ HtmlFormHidden

class HtmlFormHidden extends HtmlFormText
{
  // {{{ init()

  protected function init( HtmlForm $form )
  {
    parent::init( $form );

    $this->_hidden = true;
  }

  // }}}
  // {{{ hie()

  /**
   * Instancie un element de type caché.
   *
   * @param HtmlForm L'instance du formulaire
   * @param string Le nom de la classe a instancier
   * @param string|null Le nom du champ
   * @param string|integer|array|null La valeur par défault
   * @return HtmlFormElement
   */
  static public function hie( HtmlForm $form, $class_name )
  {
    if( func_num_args() > 2 ) $name = func_get_arg(2); else $name = null;

    if( ! is_string($name) and ! is_null($name) ) throw new InvalidArgumentException(sprintf('%::%s need a string for his 3rd parameter.',__CLASS__,__FUNCTION__));

    $element = parent::hie( $form, $class_name, $name );

    if( func_num_args() > 3 ) $value = func_get_arg(3); else $value = null;

    $element->default = $value;

    return $element;
  }

  // }}}
}

// }}}
// {{{ HtmlFormFile

class HtmlFormFile extends HtmlFormElement
{
  // {{{ init()

  protected function init( HtmlForm $form )
  {
    parent::init( $form );

    $form->setPost();
  }

  // }}}
  // {{{ getValue()

  protected function getValue()
  {
    if( isset($_FILES) and isset($_FILES[$this->name]) and is_array($_FILES[$this->name]) )
      return $_FILES[$this->name];
    else
      return null;
  }

  // }}}
  // {{{ getHtml()

  public function getHtml()
  {
    if( $this->_form->send )
      return htmlspecialchars($this->value['tmp_name']);
    elseif( $this->default )
      return htmlspecialchars($this->default);
    else
      return null;
  }

  // }}}
}

// }}}
// {{{ HtmlFormMonth

/**
 * Classe de champ de type select - month
 */
class HtmlFormMonth extends HtmlFormDropdown
{
  // {{{ $_name

  protected $_name = 'month';

  // }}}
  // {{{ $_choice

  protected $_choice = '-- Select a month --';

  // }}}
  // {{{ $_year

  protected $_year = null;

  // }}}
  // {{{ $_day

  protected $_day = null;

  // }}}
  // {{{ setOfDay()

  public function setOfDay( $day = 'day' )
  {
    if( ! is_string($day) and ! is_integer($day) and ( isset($form[(string)$day]) and ! $day instanceof HtmlFormDay ) ) throw new InvalidArgumentException(sprintf('%s::%s() need a string, integer or HtmlFormDay object for his 1st parameter',__CLASS__,__FUNCTION__));

    if( $day instanceof HtmlFormDay )
      $this->_day = $day;
    elseif( is_string($day) and isset($this->form[$day]) )
      $this->_day = $this->form[$day];
    else
      $this->_day = HtmlFormDay::parseDay($day);
  }

  // }}}
  // {{{ setOfYear()

  public function setOfYear( $year = 'year' )
  {
    if( ! is_string($year) and ! is_integer($year) and ( isset($form[(string)$year]) and ! $form[(string)$year] instanceof HtmlFormYear and ! $form[(string)$year] instanceof HtmlFormYearDown ) ) throw new InvalidArgumentException(sprintf('%s::%s() need a string, integer, HtmlFormYear object or HtmlFormYearDown object for his 1st parameter',__CLASS__,__FUNCTION__));

    if( $year instanceof HtmlFormYear or $year instanceof HtmlFormYearDown )
      $this->_year = $year;
    elseif( is_string($year) and isset($this->form[$year]) )
      $this->_year = $this->form[$year];
    else
      $this->_year = HtmlFormYear::parseYear($year);
  }

  // }}}
  // {{{ init()

  protected function init( HtmlForm $form )
  {
    parent::init( $form );

    $this->i18n = new HtmlFormMonthEnglishI18n( $this );

    $this->setCheck( array('HtmlFormMonth','checkDayOfMonth') );
    $this->setAlert( 'Month $month did\'nt have $day days', 'bad_month_for_this_day' );
  }

  // }}}
  // {{{ checkDayOfMonth()

  static protected function checkDayOfMonth( HtmlFormElement $element, HtmlForm $form )
  {
    if( ! $element instanceof HtmlFormMonth and ! $element instanceof HtmlFormDay and ! $element instanceof HtmlFormYear and ! $element instanceof HtmlFormYearDown ) throw new InvalidArgumentException(sprintf('%s::%s() need a HtmlFormMonth, HtmlFormDay, HtmlFormYear or HtmlFormYearDown object for his 1st parameter',__CLASS__,__FUNCTION__));

    if( isset($element->year) and ($element->year instanceof HtmlFormYear or $element->year instanceof HtmlFormYearDown) )
      $year = $element->year->value;
    elseif( isset($element->year) )
      $year = $element->year;
    else
      $year = $element->value;

    if( isset($element->month) and $element->month instanceof HtmlFormMonth )
      $month = $element->month->value;
    elseif( isset($element->month) )
      $month = $element->month;
    else
      $month = $element->value;

    if( isset($element->day) and $element->day instanceof HtmlFormDay )
      $day = $element->day->value;
    elseif( isset($element->day) )
      $day = $element->day;
    else
      $day = $element->value;

    if( $day > (integer)date('t', strtotime(sprintf('%s-%s-1',$year,$month))) )
      return 'bad_month_for_this_day';

    return false;
  }

  // }}}
  // {{{ parseMonth()

  /**
   * Analyse une chaine et retourne un mois.
   *
   * @return string|integer|null
   * @return integer|null
   */
  static public function parseMonth( $value )
  {
    $value = ltrim(trim($value), '0');
    if( (string)(integer)$value === (string)$value )
      return (integer)$value;
    elseif( ($time = strtotime($value)) !== false and $time !== -1 )
      return (integer)date('n', $time);
    elseif( (integer)$value === 0 )
      return 0;
    else
      return null;
  }

  // }}}
  // {{{ setDefault()

  /**
   * Modifie le label du champ.
   *
   * @param string|array|boolean
   * @return HtmlFormElement
   */
  public function setDefault( $default )
  {
    if( ! is_scalar($default) and ! is_array($default) ) throw new InvalidArgumentException(sprintf('%s::%s() need a scalar or an array for his 1st parameter',__CLASS__,__FUNCTION__));

    $this->default = self::parseMonth($default);

    return $this;
  }

  // }}}
  // {{{ hie()

  /**
   * Instancie un element de type jour.
   *
   * @param HtmlForm L'instance du formulaire
   * @param string Le nom de la classe a instancier
   * @param string|null Le nom du champ
   * @param string|integer|null|HtmlFormYear|HtmlFormYearDown Le nom d'un l'element year precedament ajouter
   * @param string|integer|null|HtmlFormDay Le nom d'un l'element day precedament ajouter
   * @return HtmlFormElement
   */
  static public function hie( HtmlForm $form, $class_name )
  {
    if( func_num_args() > 2 ) $name = func_get_arg(2); else $name = null;

    if( ! is_string($name) and ! is_null($name) ) throw new InvalidArgumentException(sprintf('%::%s need a string for his 3rd parameter.',__CLASS__,__FUNCTION__));

    $element = parent::hie( $form, $class_name, $name );

    if( func_num_args() > 3 ) $year = func_get_arg(3); else $year = null;

    if( ! is_string($year) and ( isset($form[(string)$year]) and ! $form[(string)$year] instanceof HtmlFormYear and ! $form[(string)$year] instanceof HtmlFormYearDown ) and ! is_null($year) and ! is_integer($year) ) throw new InvalidArgumentException(sprintf('%::%s() need the name of an year element already added to the form or an integer or a string for his 3rd parameter.',__CLASS__,__FUNCTION__));

    if( is_string($year) and isset($form[(string)$year]) )
      $element->setOfYear($form[$year]);
    elseif( is_string($year) or is_integer($year) )
      $element->setOfYear($year);

    if( func_num_args() > 4 ) $day = func_get_arg(4); else $day = null;

    if( ! is_string($day) and ( isset($form[(string)$day]) and ! $form[(string)$day] instanceof HtmlFormDay ) and ! is_null($day) and ! is_integer($day) ) throw new InvalidArgumentException(sprintf('%::%s() need the name of an day element already added to the form or an integer or a string for his 4th parameter.',__CLASS__,__FUNCTION__));

    if( is_string($day) and isset($form[(string)$day]) )
      $element->setOfDay($form[$day]);
    elseif( is_string($day) or is_integer($day) )
      $element->setOfDay($day);

    return $element;
  }

  // }}}
  // {{{ getTime()

  protected function getTime()
  {
    if( version_compare(phpversion(), "5.2.0", ">=") )
      return strtotime(sprintf('%s-%s-%s', (string)$this->year, (string)$this, (string)$this->day));
    else
    {
      if( $this->_year instanceof HtmlFormYear or $this->_year instanceof HtmlFormYearDown )
        $year = $this->_year->value;
      else
        $year = $this->_year;
      $month = $this->value;
      if( $this->_day instanceof HtmlFormDay )
        $day = $this->_day->value;
      else
        $day = $this->_day;
      return strtotime(sprintf('%s-%s-%s', $year, $month, $day));
    }
  }

  // }}}
  // {{{ __get()

  public function ______get( $property )
  {
    if( ! is_string($property) ) throw new InvalidArgumentException(sprintf('%s::%s() need a string for his 1st parametre',__CLASS__,__FUNCTION__));

    if( $property === 'time' )
    {
      if( version_compare(phpversion(), "5.2.0", ">=") )
        return strtotime(sprintf('%s-%s-%s', (string)$this->year, (string)$this, (string)$this->day));
      else
      {
        if( $this->_year instanceof HtmlFormYear or $this->_year instanceof HtmlFormYearDown )
          $year = $this->_year->value;
        else
          $year = $this->_year;
        $month = $this->value;
        if( $this->_day instanceof HtmlFormDay )
          $day = $this->_day->value;
        else
          $day = $this->_day;
        return strtotime(sprintf('%s-%s-%s', $year, $month, $day));
      }
    }/*
    elseif( $property === 'html' and $this->_form->send )
      return htmlspecialchars($this[self::autoslashes($this->getValue())]);
    elseif( $property === 'html' and $this->default )
      return htmlspecialchars($this[$this->default]);
    elseif( $property === 'html' )
      return null;*/

    return parent::__get( $property );
  }

  // }}}
}

// }}}
// {{{ HtmlFormBirthdate

class HtmlFormBirthdate extends HtmlFormGroupedElements
{
  // {{{ $_name

  protected $_name = 'birthdate';

  // }}}
  // {{{ init()

  protected function init( HtmlForm $form )
  {
    debug( func_get_args() );
    $this->_components['day'] = HtmlFormDay::hie( $form, 'HtmlFormDay', $this->name.'_birthday' )->setOverride( $this );
    $this->_components['month'] = HtmlFormDay::hie( $form, 'HtmlFormMonth', $this->name.'_birthmonth' )->setOverride( $this );
    $this->_components['year'] = HtmlFormYear::hie( $form, 'HtmlFormYear', $this->name.'_birthyear' )->setOverride( $this );

    parent::init( $form );
  }

  // }}}
  // {{{ setRequired()

  /**
   * Modifie le message si le champ est obligatoire.
   *
   * @param string|boolean
   * @return HtmlFormElement
   */
  public function setRequired( $required = true, $name = null )
  {
    if( ! is_string($required) and ! is_bool($required) ) throw new InvalidArgumentException;

    if( $required === true )
    {
      $this->_components['day']->setRequired( true, $this->name );
      $this->_components['month']->setRequired( true, $this->name );
      $this->_components['year']->setRequired( true, $this->name );
    }
    else
    {
      $this->_components['day']->setRequired( $required );
      $this->_components['month']->setRequired( $required );
      $this->_components['year']->setRequired( $required );
    }
  }

  // }}}
  // {{{ setDefault()

  public function setDefault( $default )
  {
    if( is_string($default) and func_num_args()==1 and preg_match('/^(\d{4}|\d{2})[\-\/\:](\d{1,2})[\-\/\:](\d{1,2})$/', $default, $match) )
    {
      $this->_components['year']->default = $match[1];
      $this->_components['month']->default = $match[2];
      $this->_components['day']->default = $match[3];
    }
    elseif( is_array($default) and count($default)==3 )
    {
      $this->_components['year']->default = array_shift($default);
      $this->_components['month']->default = array_shift($default);
      $this->_components['day']->default = array_shift($default);
    }
    elseif( func_num_args()==3 )
    {
      $this->_components['year']->default = func_get_arg(0);
      $this->_components['month']->default = func_get_arg(1);
      $this->_components['day']->default = func_get_arg(2);
    }

    return $this;
  }

  // }}}
  // {{{ getOverriedLabel()

  public function getOverridedLabel()
  {
    return $this->label;
  }

  // }}}
  // {{{ getOverridedError()

  public function getOverridedError()
  {
    if( $this->_components['day']->_error )
      $return = preg_replace_callback('/\\$(\\w+)\\b/', array($this->_components['day'],'replaceProperty'), $this->_components['day']->_error );
    elseif( $this->_components['month']->_error )
      $return = preg_replace_callback('/\\$(\\w+)\\b/', array($this->_components['month'],'replaceProperty'), $this->_components['month']->_error );
    elseif( $this->_components['year']->_error )
      $return = preg_replace_callback('/\\$(\\w+)\\b/', array($this->_components['year'],'replaceProperty'), $this->_components['year']->_error );

    if( isset($return) and $this->_alert )
      return $this->_alert;
  }

  // }}}
  // {{{ setI18n()

  /**
   * Modifie les données de traduction.
   *
   * @param HtmlFormElement
   * @param Iterator|array|string
   * @return HtmlFormElement
   */
  public function setI18n( $i18n )
  {
    $this->_components['month']->i18n = $i18n;
  }

  // }}}
  // {{{ getOverridedMonth()

  protected function getOverridedMonth()
  {
    return (string)$this->_components['month']->html;
  }

  // }}}
  // {{{ getOverridedDay()

  protected function getOverridedDay()
  {
    return (string)$this->_components['day']->html;
  }

  // }}}
  // {{{ getOverridedYear()

  protected function getOverridedYear()
  {
    return (string)$this->_components['year']->html;
  }

  // }}}
  // {{{ getOverridedMap()

  protected function getOverridedMap()
  {
    return $this->map;
  }

  // }}}
  // {{{ getOverridedExport()

  protected function getOverridedExport()
  {
    return sprintf('%04d-%02d-%02d',
      $this->_components['year']->value,
      $this->_components['month']->value,
      $this->_components['day']->value);
  }

  // }}}
}

// }}}
// {{{ HtmlFormPasswords

class HtmlFormPasswords extends HtmlFormGroupedElements
{
  // {{{ $_name

  protected $_name = 'password';

  // }}}
  // {{{ init()

  protected function init( HtmlForm $form )
  {
    $this->_components['password1'] = HtmlFormPassword::hie( $form, 'HtmlFormPassword', $this->name.'_password1' )->setOverride( $this );
    $this->_components['password2'] = HtmlFormPassword::hie( $form, 'HtmlFormPassword', $this->name.'_password2' )->setOfPassword( $this->_components['password1'] )->setOverride( $this );

    $this->_components['password1']->setOfPassword( $this->_components['password2'] );

    parent::init( $form );
  }

  // }}}
  // {{{ getOverriedLabel()

  public function getOverridedLabel()
  {
    return $this->label;
  }

  // }}}
  // {{{ getOverridedRequired()

  public function getOverridedRequired()
  {
    return $this->_required;
  }

  // }}}
  // {{{ getOverridedError()

  public function getOverridedError()
  {
    if( $this->_components['password1']->_error )
      return $this->_components['password1']->_error;
    elseif( $this->_components['password2']->_error )
      return $this->_components['password2']->_error;
  }

  // }}}
  // {{{ getValue()

  public function getValue()
  {
    return $this->_components['password1']->value;
  }

  // }}}
  // {{{ getOverridedAlert()

  public function getOverridedAlert()
  {
    return $this->_alert;
  }

  // }}}
  // {{{ getOverridedExport()

  protected function getOverridedExport()
  {
    return $this->_components['password1']->value;
  }

  // }}}
}

// }}}
// {{{ HtmlFormCountryEnglishI18n

/**
 * Classe de chaine de traduction anglaise pour les pays
 */
class HtmlFormCountryEnglishI18n extends HtmlFormI18n
{
  // {{{ $strings

  protected $strings = array(
'AF'=>"Afghanistan",
'AL'=>"Albania",
'DZ'=>"Algeria",
'AS'=>"American Samoa",
'AD'=>"Andorra",
'AG'=>"Angola",
'AI'=>"Anguilla",
'AG'=>"Antigua & Barbuda",
'AR'=>"Argentina",
'AA'=>"Armenia",
'AW'=>"Aruba",
'AU'=>"Australia",
'AT'=>"Austria",
'AZ'=>"Azerbaijan",
'BS'=>"Bahamas",
'BH'=>"Bahrain",
'BD'=>"Bangladesh",
'BB'=>"Barbados",
'BY'=>"Belarus",
'BE'=>"Belgium",
'BZ'=>"Belize",
'BJ'=>"Benin",
'BM'=>"Bermuda",
'BT'=>"Bhutan",
'BO'=>"Bolivia",
'BL'=>"Bonaire",
'BA'=>"Bosnia & Herzegovina",
'BW'=>"Botswana",
'BR'=>"Brazil",
'BC'=>"British Indian Ocean Ter",
'BN'=>"Brunei",
'BG'=>"Bulgaria",
'BF'=>"Burkina Faso",
'BI'=>"Burundi",
'KH'=>"Cambodia",
'CM'=>"Cameroon",
'CA'=>"Canada",
'IC'=>"Canary Islands",
'CV'=>"Cape Verde",
'KY'=>"Cayman Islands",
'CF'=>"Central African Republic",
'TD'=>"Chad",
'CD'=>"Channel Islands",
'CL'=>"Chile",
'CN'=>"China",
'CI'=>"Christmas Island",
'CS'=>"Cocos Island",
'CO'=>"Colombia",
'CC'=>"Comoros",
'CG'=>"Congo",
'CK'=>"Cook Islands",
'CR'=>"Costa Rica",
'CT'=>"Cote D'Ivoire",
'HR'=>"Croatia",
'CU'=>"Cuba",
'CB'=>"Curacao",
'CY'=>"Cyprus",
'CZ'=>"Czech Republic",
'DK'=>"Denmark",
'DJ'=>"Djibouti",
'DM'=>"Dominica",
'DO'=>"Dominican Republic",
'TM'=>"East Timor",
'EC'=>"Ecuador",
'EG'=>"Egypt",
'SV'=>"El Salvador",
'GQ'=>"Equatorial Guinea",
'ER'=>"Eritrea",
'EE'=>"Estonia",
'ET'=>"Ethiopia",
'FA'=>"Falkland Islands",
'FO'=>"Faroe Islands",
'FJ'=>"Fiji",
'FI'=>"Finland",
'FR'=>"France",
'GF'=>"French Guiana",
'PF'=>"French Polynesia",
'FS'=>"French Southern Ter",
'GA'=>"Gabon",
'GM'=>"Gambia",
'GE'=>"Georgia",
'DE'=>"Germany",
'GH'=>"Ghana",
'GI'=>"Gibraltar",
'GB'=>"Great Britain",
'GR'=>"Greece",
'GL'=>"Greenland",
'GD'=>"Grenada",
'GP'=>"Guadeloupe",
'GU'=>"Guam",
'GT'=>"Guatemala",
'GN'=>"Guinea",
'GY'=>"Guyana",
'HT'=>"Haiti",
'HW'=>"Hawaii",
'HN'=>"Honduras",
'HK'=>"Hong Kong",
'HU'=>"Hungary",
'IS'=>"Iceland",
'IN'=>"India",
'ID'=>"Indonesia",
'IA'=>"Iran",
'IQ'=>"Iraq",
'IR'=>"Ireland",
'IM'=>"Isle of Man",
'IL'=>"Israel",
'IT'=>"Italy",
'JM'=>"Jamaica",
'JP'=>"Japan",
'JO'=>"Jordan",
'KZ'=>"Kazakhstan",
'KE'=>"Kenya",
'KI'=>"Kiribati",
'NK'=>"Korea North",
'KS'=>"Korea South",
'KW'=>"Kuwait",
'KG'=>"Kyrgyzstan",
'LA'=>"Laos",
'LV'=>"Latvia",
'LB'=>"Lebanon",
'LS'=>"Lesotho",
'LR'=>"Liberia",
'LY'=>"Libya",
'LI'=>"Liechtenstein",
'LT'=>"Lithuania",
'LU'=>"Luxembourg",
'MO'=>"Macau",
'MK'=>"Macedonia",
'MG'=>"Madagascar",
'MY'=>"Malaysia",
'MW'=>"Malawi",
'MV'=>"Maldives",
'ML'=>"Mali",
'MT'=>"Malta",
'MH'=>"Marshall Islands",
'MQ'=>"Martinique",
'MR'=>"Mauritania",
'MU'=>"Mauritius",
'ME'=>"Mayotte",
'MX'=>"Mexico",
'MI'=>"Midway Islands",
'MD'=>"Moldova",
'MC'=>"Monaco",
'MN'=>"Mongolia",
'MS'=>"Montserrat",
'MA'=>"Morocco",
'MZ'=>"Mozambique",
'MM'=>"Myanmar",
'NA'=>"Nambia",
'NU'=>"Nauru",
'NP'=>"Nepal",
'AN'=>"Netherland Antilles",
'NL'=>"Netherlands",
'NV'=>"Nevis",
'NC'=>"New Caledonia",
'NZ'=>"New Zealand",
'NI'=>"Nicaragua",
'NE'=>"Niger",
'NG'=>"Nigeria",
'NW'=>"Niue",
'NF'=>"Norfolk Island",
'NO'=>"Norway",
'OM'=>"Oman",
'PK'=>"Pakistan",
'PW'=>"Palau Island",
'PS'=>"Palestine",
'PA'=>"Panama",
'PG'=>"Papua New Guinea",
'PY'=>"Paraguay",
'PE'=>"Peru",
'PH'=>"Philippines",
'PO'=>"Pitcairn Island",
'PL'=>"Poland",
'PT'=>"Portugal",
'PR'=>"Puerto Rico",
'QA'=>"Qatar",
'RE'=>"Reunion",
'RO'=>"Romania",
'RU'=>"Russia",
'RW'=>"Rwanda",
'NT'=>"St Barthelemy",
'EU'=>"St Eustatius",
'HE'=>"St Helena",
'KN'=>"St Kitts-Nevis",
'LC'=>"St Lucia",
'MB'=>"St Maarten",
'PM'=>"St Pierre & Miquelon",
'VC'=>"St Vincent & Grenadines",
'SP'=>"Saipan",
'SO'=>"Samoa",
'AS'=>"Samoa American",
'SM'=>"San Marino",
'ST'=>"Sao Tome & Principe",
'SA'=>"Saudi Arabia",
'SN'=>"Senegal",
'SC'=>"Seychelles",
'SS'=>"Serbia & Montenegro",
'SL'=>"Sierra Leone",
'SG'=>"Singapore",
'SK'=>"Slovakia",
'SI'=>"Slovenia",
'SB'=>"Solomon Islands",
'OI'=>"Somalia",
'ZA'=>"South Africa",
'ES'=>"Spain",
'LK'=>"Sri Lanka",
'SD'=>"Sudan",
'SR'=>"Suriname",
'SZ'=>"Swaziland",
'SE'=>"Sweden",
'CH'=>"Switzerland",
'SY'=>"Syria",
'TA'=>"Tahiti",
'TW'=>"Taiwan",
'TJ'=>"Tajikistan",
'TZ'=>"Tanzania",
'TH'=>"Thailand",
'TG'=>"Togo",
'TK'=>"Tokelau",
'TO'=>"Tonga",
'TT'=>"Trinidad & Tobago",
'TN'=>"Tunisia",
'TR'=>"Turkey",
'TU'=>"Turkmenistan",
'TC'=>"Turks & Caicos Is",
'TV'=>"Tuvalu",
'UG'=>"Uganda",
'UA'=>"Ukraine",
'AE'=>"United Arab Emirates",
'GB'=>"United Kingdom",
'US'=>"United States of America",
'UY'=>"Uruguay",
'UZ'=>"Uzbekistan",
'VU'=>"Vanuatu",
'VS'=>"Vatican City State",
'VE'=>"Venezuela",
'VN'=>"Vietnam",
'VB'=>"Virgin Islands (Brit)",
'VA'=>"Virgin Islands (USA)",
'WK'=>"Wake Island",
'WF'=>"Wallis & Futana Is",
'YE'=>"Yemen",
'ZR'=>"Zaire",
'ZM'=>"Zambia",
'ZW'=>"Zimbabwe",
'--'=>"-- Other --"
);

  // }}}
}

// }}}
// {{{ HtmlFormCountryFrenchI18n

/**
 * Classe de chaîne de traduction française pour les pays
 */
class HtmlFormCountryFrenchI18n extends HtmlFormI18n
{
  // {{{ $_choice

  protected $_choice = '-- Choisissez un pays --';

  // }}}
  // {{{ $_label

  protected $_label = 'Pays';

  // }}}
  // {{{ $strings

  protected $strings = array(
'AF' => 'Afghanistan',
'ZA' => 'Afrique du Sud',
'AL' => 'Albanie',
'DZ' => 'Alg&eacute;rie',
'DE' => 'Allemagne',
'AD' => 'Andorre',
'AO' => 'Angola',
'AI' => 'Anguilla',
'AQ' => 'Antarctique',
'AG' => 'Antigua-et-Barbuda',
'AN' => 'Antilles n&eacute;erlandaises',
'SA' => 'Arabie Saoudite',
'AR' => 'Argentine',
'AM' => 'Arm&eacute;nie',
'AW' => 'Aruba',
'AU' => 'Australie',
'AT' => 'Autriche',
'AZ' => 'Azerba&iuml;djan',
'BS' => 'Bahamas',
'BH' => 'Bahre&iuml;n',
'BD' => 'Bangladesh',
'BB' => 'Barbade (la)',
'BE' => 'Belgique',
'BZ' => 'Belize',
'BJ' => 'B&eacute;nin',
'BM' => 'Bermudes',
'BT' => 'Bhoutan',
'BY' => 'Bi&eacute;lorussie',
'BO' => 'Bolivie',
'BA' => 'Bosnie et Herz&eacute;govine',
'BW' => 'Botswana',
'BR' => 'Br&eacute;sil',
'BN' => 'Brunei',
'BG' => 'Bulgarie',
'BF' => 'Burkina-Faso',
'BI' => 'Burundi',
'KH' => 'Cambodge',
'CM' => 'Cameroun',
'CA' => 'Canada',
'CV' => 'Cap-Vert',
'CL' => 'Chili',
'CN' => 'Chine',
'CY' => 'Chypre',
'CO' => 'Colombie',
'KM' => 'Comores',
'CG' => 'Congo',
'CD' => 'Congo, R&eacute;publique du',
'KR' => 'Cor&eacute;e',
'KP' => 'Cor&eacute;e du Nord',
'CR' => 'Costa Rica',
'CI' => 'C&ocirc;te D\'Ivoire',
'HR' => 'Croatie',
'CU' => 'Cuba',
'DK' => 'Danemark',
'UM' => 'D&eacute;pendances am&eacute;ricaines du Pacifique',
'DJ' => 'Djibouti',
'DM' => 'Dominique (la)',
'EG' => '&Eacute;gypte',
'AE' => '&Eacute;mirats Arabes Unis',
'EC' => '&Eacute;quateur (R&eacute;publique de l\')',
'ER' => '&Eacute;rythr&eacute;e',
'ES' => 'Espagne',
'EE' => 'Estonie',
'VA' => '&Eacute;tat de la cit&eacute; du Vatican',
'US' => '&Eacute;tats-Unis',
'ET' => '&Eacute;thiopie',
'RU' => 'F&eacute;d&eacute;ration de Russie',
'FJ' => 'Fidji',
'FI' => 'Finlande',
'FR' => 'France',
'GA' => 'Gabon',
'GM' => 'Gambie',
'GE' => 'G&eacute;orgie',
'GS' => 'G&eacute;orgie du Sud et Sandwich du Sud (&Icirc;Ies)',
'GH' => 'Ghana',
'GI' => 'Gibraltar',
'GR' => 'Gr&egrave;ce',
'GD' => 'Grenade',
'GL' => 'Groenland',
'GP' => 'Guadeloupe (France DOM-TOM)',
'GU' => 'Guam',
'GT' => 'Guatemala',
'GN' => 'Guin&eacute;e',
'GQ' => 'Guin&eacute;e &Eacute;quatoriale',
'GW' => 'Guin&eacute;e-Bissau',
'GY' => 'Guyane',
'GF' => 'Guyane fran&ccedil;aise',
'HT' => 'Ha&iuml;ti',
'HN' => 'Honduras (le)',
'HK' => 'Hong Kong',
'HU' => 'Hongrie',
'CX' => '&Icirc;le Christmas',
'NF' => '&Icirc;le de Norfolk',
'MU' => '&Icirc;le Maurice',
'SJ' => '&Icirc;le Svalbard et Jan Mayen',
'BV' => '&Icirc;les Bouvet',
'KY' => '&Icirc;les Ca&iuml;mans',
'CC' => '&Icirc;les Cocos-Keeling',
'CK' => '&Icirc;les Cook',
'FO' => '&Icirc;les F&eacute;ro&eacute;',
'HM' => '&Icirc;les Heard et Mc Donald',
'FK' => '&Icirc;les Malouines',
'MH' => '&Icirc;les Marshall',
'SB' => '&Icirc;les Salomon',
'TK' => '&Icirc;les Tokelau',
'TC' => '&Icirc;les Turks et Ca&iuml;cos',
'VI' => '&Icirc;les Vierges am&eacute;ricaines',
'VG' => '&Icirc;les Vierges britanniques',
'IN' => 'Inde',
'ID' => 'Indon&eacute;sie',
'IQ' => 'Irak',
'IR' => 'Iran',
'IE' => 'Irlande',
'IS' => 'Islande',
'IL' => 'Isra&euml;l',
'IT' => 'Italie',
'LY' => 'Jamahiriya arabe libyenne (Lybie)',
'JM' => 'Jama&iuml;que',
'JP' => 'Japon',
'JO' => 'Jordanie',
'KZ' => 'Kazakhstan',
'KE' => 'Kenya',
'KG' => 'Kirghizistan',
'KI' => 'Kiribati',
'KW' => 'Kowe&iuml;t',
'LS' => 'Lesotho',
'LV' => 'Lettonie',
'LB' => 'Liban',
'LR' => 'Liberia',
'LI' => 'Liechtenstein',
'LT' => 'Lituanie',
'LU' => 'Luxembourg',
'MO' => 'Macao',
'MK' => 'Mac&eacute;doine, Ex-R&eacute;publique yougoslave de',
'MG' => 'Madagascar',
'MY' => 'Malaisie',
'MW' => 'Malawi',
'MV' => 'Maldives',
'ML' => 'Mali',
'MT' => 'Malte',
'MP' => 'Mariannes du Nord (&Icirc;les du Commonwealth)',
'MA' => 'Maroc',
'MQ' => 'Martinique (France DOM-TOM)',
'MR' => 'Mauritanie',
'YT' => 'Mayotte',
'MX' => 'Mexique',
'FM' => 'Micron&eacute;sie',
'MD' => 'Moldavie',
'MC' => 'Monaco',
'MN' => 'Mongolie',
'MS' => 'Montserrat',
'MZ' => 'Mozambique',
'MM' => 'Myanmar (Union de)',
'NA' => 'Namibie',
'NR' => 'Nauru (R&eacute;publique de)',
'NP' => 'N&eacute;pal',
'NI' => 'Nicaragua',
'NE' => 'Niger',
'NG' => 'Nig&eacute;ria',
'NU' => 'Niue',
'NO' => 'Norv&egrave;ge',
'NC' => 'Nouvelle Cal&eacute;donie',
'NZ' => 'Nouvelle Z&eacute;lande',
'OM' => 'Oman',
'UG' => 'Ouganda',
'UZ' => 'Ouzb&eacute;kist&auml;n',
'PK' => 'Pakistan',
'PW' => 'Palau',
'PA' => 'Panama',
'PG' => 'Papouasie Nouvelle-Guin&eacute;e',
'PY' => 'Paraguay',
'NL' => 'Pays-Bas',
'PE' => 'P&eacute;rou',
'PH' => 'Philippines',
'PN' => 'Pitcairn (&Icirc;les)',
'PL' => 'Pologne',
'PF' => 'Polyn&eacute;sie fran&ccedil;aise (DOM-TOM)',
'PR' => 'Porto Rico',
'PT' => 'Portugal',
'QA' => 'Qatar',
'SY' => 'R&eacute;publique arabe syrienne',
'CF' => 'R&eacute;publique Centrafricaine',
'LA' => 'R&eacute;publique d&eacute;mocratique populaire du Laos',
'DO' => 'R&eacute;publique Dominicaine',
'CZ' => 'R&eacute;publique tch&egrave;que',
'RE' => 'R&eacute;union (&Icirc;le de la)',
'RO' => 'Roumanie',
'UK' => 'Royaume-Uni',
'RW' => 'Rwanda',
'SH' => 'Sainte H&eacute;l&egrave;ne',
'LC' => 'Saint-Lucie',
'SM' => 'Saint-Marin',
'PM' => 'Saint-Pierre-et-Miquelon (France DOM-TOM)',
'VC' => 'Saint-Vincent et les Grenadines',
'SV' => 'Salvador',
'WS' => 'Samoa',
'AS' => 'Samoa am&eacute;ricaines',
'ST' => 'S&acirc;o Tom&eacute; et Prince',
'SN' => 'S&eacute;n&eacute;gal',
'SC' => 'Seychelles',
'SL' => 'Sierra Leone',
'SG' => 'Singapour',
'SK' => 'Slovaquie',
'SI' => 'Slov&eacute;nie',
'SO' => 'Somalie',
'SD' => 'Soudan',
'LK' => 'Sri Lanka',
'KN' => 'St Christopher et Nevis (&Icirc;les)',
'SE' => 'Su&egrave;de',
'CH' => 'Suisse',
'SR' => 'Suriname',
'SZ' => 'Swaziland',
'TW' => 'Taiwan',
'TJ' => 'Tajikistan',
'TZ' => 'Tanzanie',
'TD' => 'Tchad',
'TF' => 'Terres Australes fran&ccedil;aises (DOM-TOM)',
'IO' => 'Territoires Britanniques de l\'oc&eacute;an Indien',
'TH' => 'Tha&iuml;lande',
'TP' => 'Timor oriental (partie orientale)',
'TG' => 'Togo',
'TO' => 'Tonga',
'TT' => 'Trinit&eacute;-et-Tobago',
'TN' => 'Tunisie',
'TM' => 'Turkm&eacute;nistan',
'TR' => 'Turquie',
'TV' => 'Tuvalu (&Icirc;les)',
'UA' => 'Ukraine',
'UY' => 'Uruguay',
'VU' => 'Vanuatu (R&eacute;publique de)',
'VE' => 'Venezuela',
'VN' => 'Vietnam',
'WF' => 'Wallis et Futuna',
'YE' => 'Y&eacute;men',
'YU' => 'Yougoslavie',
'ZM' => 'Zambie',
'ZW' => 'Zimbabwe',
'--' => '-- Autre --' );

  // }}}
  // {{{ current()

  public function current()
  {
    return html_entity_decode(current($this->strings));
  }

  // }}}
}

// }}}
// {{{ HtmlFormPostalCodeGroupFrenchI18n

/**
 * Classe de chaîne de traduction française pour les départements.
 */
class HtmlFormPostalCodeGroupFrenchI18n extends HtmlFormI18n
{
  // {{{ $_choice

  protected $_choice = '-- Choisissez un département --';

  // }}}
  // {{{ $_label

  protected $_label = 'Département';

  // }}}
  // {{{ $string

  protected $strings = array(
'01' => '01 Ain',
'02' => '02 Aisne',
'03' => '03 Allier',
'04' => '04 Alpes-de-Haute-Provence',
'05' => '05 Hautes-Alpes',
'06' => '06 Alpes-Maritimes',
'07' => '07 Ardèche',
'08' => '08 Ardennes',
'09' => '09 Ariège',
'10' => '10 Aube',
'11' => '11 Aude',
'12' => '12 Aveyron',
'13' => '13 Bouches-du-Rhône',
'14' => '14 Calvados',
'15' => '15 Cantal',
'16' => '16 Charente',
'17' => '17 Charente-Maritime',
'18' => '18 Cher',
'19' => '19 Corrèze',
'2A' => '2A Corse-du-Sud',
'21' => '21 Côte d\'Or',
'22' => '22 Côtes-\'Armor',
'23' => '23 Creuse',
'24' => '24 Dordogne',
'25' => '25 Doubs',
'26' => '26 Drôme',
'27' => '27 Eure',
'28' => '28 Eure-et-Loire',
'29' => '29 Finistère',
'30' => '30 Gard',
'31' => '31 Haute-Garonne',
'32' => '32 Gers',
'33' => '33 Gironde',
'34' => '34 Hérault',
'35' => '35 Ille-et-Vilaine',
'36' => '36 Indre',
'37' => '37 Indre-et-Loire',
'38' => '38 Isère',
'39' => '39 Jura',
'40' => '40 Landes',
'41' => '41 Loir-et-Cher',
'42' => '42 Loire',
'43' => '43 Haute-Loire',
'44' => '44 Loire-Atlantique',
'45' => '45 Loiret',
'46' => '46 Lot',
'47' => '47 Lot-et-Garonne',
'48' => '48 Lozère',
'49' => '49 Maine-et-Loire',
'50' => '50 Manche',
'51' => '51 Marne',
'52' => '52 Haute-Marne',
'53' => '53 Mayenne',
'54' => '54 Meurthe-et-Moselle',
'55' => '55 Meuse',
'56' => '56 Morbihan',
'57' => '57 Moselle',
'58' => '58 Nièvre',
'59' => '59 Nord',
'60' => '60 Oise',
'61' => '61 Orne',
'62' => '62 Pas-de-Calais',
'63' => '63 Puy-de-Dôme',
'64' => '64 Pyrénées-Atlantiques',
'65' => '65 Hautes-Pyrénées',
'66' => '66 Pyrénées-Orientales',
'67' => '67 Bas-Rhin',
'68' => '68 Haut-Rhin',
'69' => '69 Rhône',
'70' => '70 Haute-Saône',
'71' => '71 Saône-et-Loire',
'72' => '72 Sarthe',
'73' => '73 Savoie',
'74' => '74 Haute-Savoie',
'75' => '75 Seine',
'76' => '76 Seine-Maritime',
'77' => '77 Seine-et-Marne',
'78' => '78 Yvelines',
'79' => '79 Deux-Sèvres',
'80' => '80 Somme',
'81' => '81 Tarn',
'82' => '82 Tarn-et-Garonne',
'83' => '83 Var',
'84' => '84 Vaucluse',
'85' => '85 Vendée',
'86' => '86 Vienne',
'87' => '87 Haute-Vienne',
'88' => '88 Vosges',
'89' => '89 Yonne',
'90' => '90 Territoire-de-Belfort',
'91' => '91 Essonne',
'92' => '92 Hauts-de-Seine',
'93' => '93 Seine-Saint-Denis',
'94' => '94 Val-de-Marne',
'95' => '95 Val-d\'Oise',
'2B' => '2B Haute-Corse',
'971' => '971 La Guadeloupe',
'972' => '972 La Martinique',
'974' => '974 La Réunion',
'975' => '975 Territoire d\'outre-mer',
'99' => '99 Principauté de Monaco'
    );

  // }}}
  // {{{ current()

  public function current()
  {
    return html_entity_decode(current($this->strings));
  }

  // }}}
}

// }}}
// {{{ HtmlFormMonthEnglishI18n

/**
 * Classe de chaine de traduction anglaise pour les mois
 */
class HtmlFormMonthEnglishI18n extends HtmlFormI18n
{
  // {{{ $strings

  protected $strings = array('1'=>
'January',
'February',
'March',
'April',
'May',
'June',
'July',
'August',
'September',
'October',
'November',
'December');

  // }}}
}

// }}}
// {{{ HtmlFormMonthFrenchI18n

/**
 * Classe de chaine de traduction anglaise pour les mois
 */
class HtmlFormMonthFrenchI18n extends HtmlFormI18n
{
  // {{{ $_choice

  protected $_choice = '-- Choisissez un moi --';

  // }}}
  // {{{ $strings

  protected $strings = array('1'=>
'Janvier',
'F&eacute;vrier',
'Mars',
'Avril',
'Mai',
'Juin',
'Juillet',
'Ao&ucirc;t',
'Septembre',
'Octobre',
'Novembre',
'D&eacute;cembre');

  // }}}
  // {{{ current()

  public function current()
  {
    return html_entity_decode(current($this->strings));
  }

  // }}}
}

// }}}
// {{{ HtmlFormHtml

class HtmlFormHtml extends HtmlFormCustomElement
{
  // {{{ hie()

  /**
   * Instancie un element
   *
   * @param HtmlForm
   * @param string Le nom de la classe a instancier
   * @param string Le nom de l'element
   * @return HtmlFormCustomElement
   */
  static public function hie( HtmlForm $form, $class_name )
  {
    if( ! is_string($class_name) ) throw new InvalidArgumentException(sprintf('%s::%s need a string for his 2nd parametre.',__CLASS__,__FUNCTION__));

    if( func_num_args() > 2 ) $html = $name = func_get_arg(2); else $html = $name = null;
    if( func_num_args() > 3 ) $html = func_get_arg(3); else $html = null;

    if( ! is_string($html) ) throw new InvalidArgumentException(sprintf('%::%s need a string for his 3rd parameter.',__CLASS__,__FUNCTION__));

    $element = new $class_name( $form, $name );
    $element->_html = $html;

    return $element;
  }

  // }}}
  // {{{ $_html

  protected $_html = null;

  // }}}
  // {{{ $_valid

  private $_valid = true;

  // }}}
  // {{{ init()

  public function init( HtmlForm $form )
  {

  }

  // }}}
  // {{{ currentElement()

  /**
   * @ignore
   */
  public function currentElement()
  {
    return $this;
  }

  // }}}
  // {{{ nextElement()

  /**
   * @ignore
   */
  public function nextElement()
  {
    $this->_valid = false;
  }

  // }}}
  // {{{ elementKey()

  /**
   * @ignore
   */
  public function elementKey()
  {
    return $this->__get( 'name' );
  }

  // }}}
  // {{{ rewindElements()

  /**
   * @ignore
   */
  public function rewindElements()
  {
    $this->_valid = true;
  }

  // }}}
  // {{{ elementIsValid()

  /**
   * @ignore
   */
  public function elementIsValid()
  {
    return $this->_valid;
  }

  // }}}
  // {{{ getRequired()

  protected function getRequired()
  {
    return false;
  }

  // }}}
  // {{{ getValue()

  protected function getValue()
  {
    return null;
  }

  // }}}
  // {{{ getMap()

  protected function getMap()
  {
    return null;
  }

  // }}}
}

// }}}
// {{{ BadPropertyException

/**
 * @ignore
 */
if( ! class_exists('BadPropertyException') )
{
  class BadPropertyException extends LogicException
  {
  }
}

// }}}
// {{{ HtmlOutInterface

/**
 * Interface pour les decorateurs
 */
interface HtmlOutInterface
{
  // {{{ display()

  /**
   * Affiche le formulaire
   *
   * @param HtmlForm
   */
  static public function display( HtmlForm $form );

  // }}}
  // {{{ fetch()

  /**
   * Retourne le code HTML du formulaire
   *
   * @param HtmlForm
   */
  static public function fetch( HtmlForm $form );

  // }}}
}

// }}}
// {{{ HtmlOut

/**
 * Decorateur pas default
 */
class HtmlOut implements HtmlOutInterface
{
  // {{{ display()

  static public function display( HtmlForm $form )
  {
    echo self::fetch( $form );
  }

  // }}}
  // {{{ fetch()

  static public function fetch( HtmlForm $form )
  {
    $form->doValid();

    if( is_string($form->anchor) )
      $anchor = '#'.$form->anchor;
    else
      $anchor = null;

    $buffer = <<<HTML
<form name="{$form->name}" method="{$form->method}" action="{$form->action}{$anchor}" enctype="multipart/form-data">

HTML;

    foreach( $form as $element )
    {
      $result = '';
      switch( true )
      {
      case $element instanceof HtmlFormHidden :
        $result = self::hidden( $element );
        break;
      case $element instanceof HtmlFormText :
        $result = self::text( $element );
        break;
      case $element instanceof HtmlFormDropdown :
        $result = self::select( $element );
        break;
      case $element instanceof HtmlFormButton :
        $result = self::button( $element );
        break;
      case $element instanceof HtmlFormRadio :
        $result = self::radio( $element );
        break;
      case $element instanceof HtmlFormCheckbox :
        $result = self::checkbox( $element );
        break;
      case $element instanceof HtmlFormCheckboxs :
        $result = self::checkboxs( $element );
        break;
      case $element instanceof HtmlFormHtml :
        $result = self::html( $element );
        break;
      case $element instanceof HtmlFormFile :
        $result = self::file( $element );
        break;
      case $element instanceof HtmlFormArea :
        $result = self::area( $element );
        break;
      default :
        trigger_error(sprintf('The decorateur %s cannot display the field %s',__CLASS__,get_class($element)),E_USER_WARNING);
      }
      if( ! is_string($result) )
        throw new UnexpectedValueException( sprintf('%s::%s() must return a string',__CLASS__,$method_name) );
      $buffer .= $result;
    }

    $buffer .= <<<HTML
</form>

HTML;

    return $buffer;
  }

  // }}}
  // {{{ prepare_fetch()

  /**
   * Prepare quelques donnees communes
   */
  static private function prepare_fetch( HtmlFormCustomElement $element, &$alert, &$error, &$readonly, &$label, &$class )
  {
    if( $element->override instanceof HtmlFormGroupedElements )
      $grouped_name = $element->override->name;

    if( is_string($element->error) )
      $alert = <<<HTML
    <div class="alert"><p>{$element->error}</p></div>

HTML;
    else
      $alert = '';

    if( $element->error )
      $error = 'error';
    else
      $error = '';

    if( $element->readonly )
      $readonly = ' readonly="readonly"';
    else
      $readonly = '';

    if( is_null($element->first) or $element->first === true )
      $label = <<<HTML
    <div class="label"><label for="{$element->id}">{$element->label}</label></div>

HTML;
    else
      $label = '';

    if( is_null($element->last) or $element->last === true )
      null;
    else
      $alert = '';

    $class = strtr( $element->name, array('_'=>'-') );
  }

  // }}}
  // {{{ html

  static private function html( HtmlFormHtml $e )
  {
    if( $e->label )
    $label = <<<HTML
      <div class="label"><label for="{$e->id}">{$e->label}</label></div>

HTML;
    else
      $label = '';

    return <<<HTML
  <div class="element elementhtml {$e->name}">
{$label}
{$e->html}  </div>

HTML;

  }

  // }}}
  // {{{ hidden()

  static private function hidden( HtmlFormElement $e )
  {
    self::prepare_fetch( $e, $alert, $error, $readonly, $label, $class );

    return <<<HTML
  <div class="element elementhidden {$class} {$error}">
    <input id="{$e->id}" type="hidden" name="{$e->name}" value="{$e->html}" id="{$e->id}"{$readonly}/>
{$alert}  </div>

HTML;

  }

  // }}}
  // {{{ area()

  static protected function area( HtmlFormArea $e )
  {
    self::prepare_fetch( $e, $alert, $error, $readonly, $label, $class );

    if( $e->readonly )
      $readonly = 'disabled="true"';
    else
      $readonly = '';

      return <<<HTML
  <div class="element elementtext {$class} {$error}">
{$label}    <div class="field"><textarea id="{$e->id}" name="{$e->name}" {$readonly}>{$e->html}</textarea></div>
{$alert}  </div>

HTML;
  }

  // }}}
  // {{{ text()

  static protected function text( HtmlFormText $e )
  {
    self::prepare_fetch( $e, $alert, $error, $readonly, $label, $class );

    if( $e->max_length )
      $max_lenght = ' maxlength="'.$e->max_length.'"';
    else
      $max_lenght = '';

    if( $e->password )
      $type = 'password';
    elseif( $e->hidden )
    {
      $type = 'hidden';
      $label = null;
    }
    else
      $type = 'text';

      return <<<HTML
  <div class="element elementtext {$class} {$error}">
{$label}    <div class="field"><input id="{$e->id}" type="{$type}" name="{$e->name}" value="{$e->html}" {$readonly}{$max_lenght}/></div>
{$alert}  </div>

HTML;
  }

  // }}}
  // {{{ file()

  static protected function file( HtmlFormFile $e )
  {
    self::prepare_fetch( $e, $alert, $error, $readonly, $label, $class );

    if( $e->hidden )
    {
      $type = 'hidden';
      $label = null;
    }
    else
      $type = 'file';

      return <<<HTML
  <div class="element elementtext {$class} {$error}">
{$label}    <div class="field"><input id="{$e->id}" type="{$type}" name="{$e->name}" value="{$e->html}" id="{$e->id}"{$readonly}/></div>
{$alert}  </div>

HTML;
  }

  // }}}
  // {{{ select()

  static protected function select( HtmlFormDropdown $e )
  {
    self::prepare_fetch( $e, $alert, $error, $readonly, $label, $class );

    if( is_string($e->choice) and ! $e->readonly )
      $options = <<<HTML
      <option value="">{$e->choice}</option>

HTML;
    else
      $options = '';

    $i = 0;
    foreach( $e as $key => $value )
    {
      $i += 1;
      if( is_array($e->value) )
        $selected = isset($e->value[$key]);
      elseif( is_scalar($e->value) )
        $selected = ($e->value==$key);
      else
        $selected = false;
      if( $selected )
        $selected = ' selected="selected"';
      if( $e->readonly and ! $selected )
        continue;
      //$value = htmlspecialchars($value);
      $key = urlencode($key);
      $options .= <<<HTML
      <option value="{$key}" id="{$e->id}{$i}"{$selected}>{$value}</option>

HTML;
    }
    return <<<HTML
  <div class="element elementselect {$class} {$error}">
{$label}    <div class="field"><select name="{$e->name}" id="{$e->id}"{$readonly}>
{$options}    </select></div>
{$alert}  </div>

HTML;
  }

  // }}}
  // {{{ button()

  static protected function button( HtmlFormButton $e )
  {
    if( $e->hidden )
      return self::hidden( $e );

    self::prepare_fetch( $e, $alert, $error, $readonly, $label, $class );

    if( $e instanceof HtmlFormSubmit )
      $type = 'submit';
    else
      $type = 'button';
    return <<<HTML
  <div class="element elementbutton {$class} {$error}">
    <div class="field"><input type="{$type}" name="{$e->name}" value="{$e->label}"{$readonly}/></div>
{$alert}  </div>

HTML;
  }

  // }}}
  // {{{ radio()

  static protected function radio( HtmlFormRadio $e )
  {
    self::prepare_fetch( $e, $alert, $error, $readonly, $label, $class );

    $inputs = '';

    $i = 0;
    foreach( $e as $key => $value )
    {
      $key = urlencode($key);
      //$value = htmlspecialchars($value);
      $i += 1;
      if( $e->value==$key )
        $checked = ' checked="checked"';
      else
        $checked = false;
      $inputs .= <<<HTML
    <div class="field">
      <input type="radio" name="{$class}" value="{$key}" id="{$e->id}{$i}"{$checked}{$readonly}><label for="{$e->id}{$i}">{$value}</label>
    </div>
HTML;
    }
    return <<<HTML
  <div class="element elementradio {$class} {$error}">
{$label}
{$inputs}
{$alert}  </div>

HTML;
  }

  // }}}
  // {{{ checkbox

  static protected function checkbox( HtmlFormCheckbox $e )
  {
    self::prepare_fetch( $e, $alert, $error, $readonly, $label, $class );

    if( $e->value=='1' )
      $checked = ' checked="checked"';
    else
      $checked = false;

    return <<<HTML
  <div class="element elementcheckbox {$class} {$error}">
    <div class="field"><input type="checkbox" name="{$e->name}" value="1" id="{$e->id}"{$checked}{$readonly}/><label for="{$e->id}">{$e->label}</label></div>
{$alert}  </div>

HTML;
  }

  // }}}
  // {{{ checkboxs()

  static protected function checkboxs( HtmlFormCheckboxs $e )
  {
    self::prepare_fetch( $e, $alert, $error, $readonly, $label, $class );

    $inputs = '';
    $i = 0;
    foreach( $e as $key => $value )
    {
      $i += 1;
      $checked = false;
      if( is_string($e->value) and isset($e->values[$e->value]) )
        $ckeched = true;
      elseif( is_array($e->value) )
        foreach( $e->value as $k => $v )
          $checked |= $key == $v;
      if( $checked )
        $checked = ' checked="checked"';
      //$value = htmlspecialchars($value);
      $key = urlencode($key);
      $inputs .= <<<HTML
    <div class="field">
      <input type="checkbox" name="{$e->name}[]" value="{$key}" id="{$e->id}{$i}"{$checked}{$readonly}><label for="{$e->id}{$i}">{$value}</label>
    </div>
HTML;
    }
    return <<<HTML
  <div class="element elementcheckboxs {$class} {$error}">
{$label}
{$inputs}
{$alert}  </div>

HTML;
  }

  // }}}
}

// }}}

?>
