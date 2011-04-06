<h1>A complete example with french translation</h1>
<hr/>
<?php

require_once( '../HtmlForm.php' );
require_once( 'debug.php' );

// extend the base country class
class HtmlFormPays extends HtmlFormCountry
{
  // rename the field
  protected $_name = 'pays';

  protected function init( HtmlForm $form )
  {
    parent::init( $form );

    // use the french class translation : HtmlFormCountryFrenchI18n
    $this->i18n = new HtmlFormCountryFrenchI18n( $this );
  }
}

// executed when the form being validate
function valid( HtmlForm $form )
{
  echo <<<HTML
<h1>Valid!</h1>
<pre>INSERT users {$form->mysqlSet()} ON DUPLICATE KEY UPDATE {$form->mysqlDuplicateValues()}</pre>

HTML;
  exit;
}

// a callback function to check the value of the country field
function verif_pays( HtmlFormElement $element, HtmlForm $form )
{
  if( $element->value == 'FR' )
    return 'noFR';
  else
    return false;
}

// create a new form ...
$form = HtmlForm::hie('inscription')->get

  // ... add some field
  ->radio('civilite')
    ->label('Civilité')
    ->required('Indiquez votre civilité')
    ->values('mademoiselle','madame','monsieur')

  ->text('nom')
    ->required('Vous devez saisir votre nom')

  ->text('prenom')
    ->label('Prénom')
    ->required('Vous devez saisir votre prénom')

  ->dropdown('age')
    ->choice('-- Choissez --')
    ->label('Votre age')
    ->values(array('-18'=>'moins de 18 ans','18-35'=>'entre 18 et 35 ans','+35'=>'plus de 35 ans'))

  ->birthdate('date_naissance','yeardown')
    ->label('Date de naissance')
    ->alert('Vous avez saisie une date invalide')
    ->required(true)
    ->i18n('french')

  ->email()
    ->label('Adresse mél')
    ->alert('Vous devez saisir une adresse mél valide')

  ->pays()
    ->check('verif_pays')
    ->alert(array(
      true=>'Utilisez la liste déroulante svp !',
      'noFR'=>'Vous ne pouvez pas habiter en france'))

  ->checkboxs('sante')
   ->label('Comment vous santez-vous ?')
   ->values('Bien','Mieux','Pas mal','Parfait')

  ->subscribe('email')
    ->label('Souhaitez-vous recevoir la newsletter')
    ->required('Vous devez saisir votre adresse mél pour recevoir la newsletter')

  ->submit()
    ->label('Envoyer')

  ->onValid('valid');

// display the form
HtmlOut::display( $form );

?>
<style>
form div.element { margin: 10px 0px; }
form div.element div.label { font-weight: bold; font-size: 0.8em; margin-top: 10px; }
form div.error { background: #f99; }
form div.alert p { margin: 0px; }
</style>
