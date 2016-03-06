<?php
/**
 * Table Definition for confirm_address
 */

class Confirm_address extends Managed_DataObject 
{
    public $__table = 'confirm_address';                 // table name
    public $code;                            // varchar(32)  primary_key not_null
    public $user_id;                         // int(4)   not_null
    public $address;                         // varchar(191)   not_null   not 255 because utf8mb4 takes more space
    public $address_extra;                   // varchar(191)   not_null   not 255 because utf8mb4 takes more space
    public $address_type;                    // varchar(8)   not_null
    public $claimed;                         // datetime()  
    public $sent;                            // datetime()  
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'code' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'good random code'),
                'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user who requested confirmation'),
                'address' => array('type' => 'varchar', 'length' => 191, 'not null' => true, 'description' => 'address (email, xmpp, SMS, etc.)'),
                'address_extra' => array('type' => 'varchar', 'length' => 191, 'description' => 'carrier ID, for SMS'),
                'address_type' => array('type' => 'varchar', 'length' => 8, 'not null' => true, 'description' => 'address type ("email", "xmpp", "sms")'),
                'claimed' => array('type' => 'datetime', 'description' => 'date this was claimed for queueing'),
                'sent' => array('type' => 'datetime', 'description' => 'date this was sent for queueing'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('code'),
            'foreign keys' => array(
                'confirm_address_user_id_fkey' => array('user', array('user_id' => 'id')),
            ),
        );
    }

    static function getByAddress($address, $addressType)
    {
        $ca = new Confirm_address();

        $ca->address      = $address;
        $ca->address_type = $addressType;

        if (!$ca->find(true)) {
            throw new NoResultException($ca);
        }

        return $ca;
    }

    static function saveNew($user, $address, $addressType, $extra=null)
    {
        $ca = new Confirm_address();

        if (!empty($user)) {
            $ca->user_id = $user->id;
        }

        $ca->address       = $address;
        $ca->address_type  = $addressType;
        $ca->address_extra = $extra;
        $ca->code          = common_confirmation_code(64);

        $ca->insert();

        return $ca;
    }

    public function getAddress()
    {
        return $this->address;
    }

    public function getAddressType()
    {
        return $this->address_type;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getProfile()
    {
        return Profile::getByID($this->user_id);
    }

    public function getUrl()
    {
        return common_local_url('confirmaddress', array('code' => $this->code));
    }

    /**
     * Supply arguments in $args. Currently known args:
     *  headers     Array with headers (only used for email)
     *  nickname    How we great the user (defaults to nickname, but can be any string)
     *  sitename    Name we sign the email with (defaults to sitename, but can be any string)
     *  url         The confirmation address URL.
     */
    public function sendConfirmation(array $args=array())
    {
        common_debug('Sending confirmation URL for user '._ve($this->user_id).' using '._ve($this->address_type));

        $defaults = [
                     'headers'  => array(),
                     'nickname' => $this->getProfile()->getNickname(),
                     'sitename' => common_config('site', 'name'),
                     'url'      => $this->getUrl(),
                    ];
        foreach (array_keys($defaults) as $key) {
            if (!isset($args[$key])) {
                $args[$key] = $defaults[$key];
            }
        }

        switch ($this->getAddressType()) {
        case 'email':
            $this->sendEmailConfirmation($args);
            break;
        default:
            throw ServerException('Unable to handle confirm_address address type: '._ve($this->address_type));
        }
    }

    public function sendEmailConfirmation(array $args=array())
    {
        // TRANS: Subject for address confirmation email.
        $subject = _('Email address confirmation');

        // TRANS: Body for address confirmation email.
        // TRANS: %1$s is the addressed user's nickname, %2$s is the StatusNet sitename,
        // TRANS: %3$s is the URL to confirm at.
        $body = sprintf(_("Hey, %1\$s.\n\n".
                          "Someone just entered this email address on %2\$s.\n\n" .
                          "If it was you, and you want to confirm your entry, ".
                          "use the URL below:\n\n\t%3\$s\n\n" .
                          "If not, just ignore this message.\n\n".
                          "Thanks for your time, \n%2\$s\n"),
                        $args['nickname'],
                        $args['sitename'],
                        $args['url']);

        require_once(INSTALLDIR . '/lib/mail.php');
        return mail_to_user($this->getProfile()->getUser(), $subject, $body, $args['headers'], $this->getAddress());
    }

    public function delete($useWhere=false)
    {
        $result = parent::delete($useWhere);

        if ($result === false) {
            common_log_db_error($confirm, 'DELETE', __FILE__);
            // TRANS: Server error displayed when an address confirmation code deletion from the
            // TRANS: database fails in the contact address confirmation action.
            throw new ServerException(_('Could not delete address confirmation.'));
        }
        return $result;
    }
}
