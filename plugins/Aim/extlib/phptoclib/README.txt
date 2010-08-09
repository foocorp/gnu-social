phpTOCLib version 1.0 RC1

This is released under the LGPL. AIM,TOC,OSCAR, and all other related protocols/terms are 
copyright AOL/Time Warner. This project is in no way affiliated with them, nor is this
project supported by them.

Some of the code is loosely based off of a script by Jeffrey Grafton. Mainly the decoding of packets, and the
function for roasting passwords is entirly his.

TOC documentation used is available at http://simpleaim.sourceforge.net/docs/TOC.txt


About:
phpTOCLib aims to be a PHP equivalent to the PERL module NET::AIM. Due to some limitations, 
this is difficult. Many features have been excluded in the name of simplicity, and leaves
you alot of room to code with externally, providing function access to the variables that
need them.

I have aimed to make this extensible, and easy to use, therefore taking away some built in
functionality that I had originally out in. This project comes after several months of
researching the TOC protocol.

example.php is included with the class. It needs to be executed from the command line
(ie:php -q testscript.php) and you need to call php.exe with the -q
example is provided as a demonstaration only. Though it creats a very simple, functional bot, it lacks any sort of commands, it merely resends the message it recieves in reverse.


Revisions:

-----------------------------------
by Rajiv Makhijani
(02/24/04)
	 - Fixed Bug in Setting Permit/Deny Mode
	 - Fixes so Uninitialized string offset notice doesn't appear
	 - Replaced New Lines Outputed for Each Flap Read with " . " so
	   that you can still tell it is active but it does not take so much space
	 - Removed "eh?" message
	 - Added MySQL Database Connection Message
	 - New Functions:
		update_profile(profile data string, powered by boolean)
			* The profile data string is the text that goes in the profile.
			* The powered by boolean if set to true displays a link to the
			  sourceforge page of the script.
(02/28/04)
	- Silent option added to set object not to output any information
		- To follow silent rule use sEcho function instead of Echo
-----------------------------------
by Jeremy (pickleman78)
(05/26/04) beta 1 release
	-Complete overhaul of class design and message handling
	-Fixed bug involving sign off after long periods of idling
	-Added new function $Aim->registerHandler
	-Added the capability to handle all AIM messages
		-Processing the messages is still the users responsibility
	-Did a little bit of code cleanup
	-Added a few internal functions to make the classes internal life easier
	-Improved AIM server error message processing
	-Updated this document (hopefully Rajiv will clean it up some, since I'm a terrible documenter)
-------------------------------------------------------------------------------------------------------------



Functions:

Several methods are provided in the class that allow for simple access to some of the 
common features of AIM. Below are details.

$Aim->Aim($sn,$password,$pdmode, $silent=false)
The constructor, it takes 4 arguments. 
$sn is your screen name
$password is you password, in plain text
$pdmode is the permit deny mode. This can be as follows:
1 - Allow All
2 - Deny All
3 - Permit only those on your permit list
4 - Permit all those not on your deny list
$silent if set to true prints out nothing

So, if your screen-name is JohnDoe746 and your password is fertu, and you want to allow
all users of the AIM server to contact you, you would code as follows
$myaim=new Aim("JohnDoe746","fertu",1);


$Aim->add_permit($buddy)
This adds the buddy passed to the function to your permit list.
ie: $myaim->add_permit("My friend22");

$Aim->block_buddy($buddy)
Blocks a user. This will switch your pd mode to 4. After using this, for the user to remain
out of contact with you, it is required to provide the constructor with a pd mode of 4
ie:$myaim->block_buddy("Annoying guy 4");

$Aim->send_im($to,$message,$auto=false)
Sends $message to $user. If you set the 3rd argument to true, then the recipient will receive it in
the same format as an away message. (Auto Response from me:)
A message longer than 65535 will be truncated
ie:$myaim->send_im("myfriend","This is a happy message");

$Aim->set_my_info()
Sends an update buddy command to the server and allows some internal values about yourself
to be set.
ie:$myaim->set_my_info();

$Aim->signon()
Call this to connect to the server. This must be called before any other methods will work
properly
ie:$mybot->signon();

$Aim->getLastReceived()
Returns $this->myLastReceived['decoded']. This should be the only peice of the gotten data
you need to concern yourself with. This is a preferred method of accessing this variable to prevent
accidental modification of $this->myLastReceived. Accidently modifying this variable can
cause some internal failures.

$Aim->read_from_aim()
This is a wrapper for $Aim->sflap_read(), and only returns the $this->myLastReceived['data']
portion of the message. It is preferred that you do not call $Aim->sflap_read() and use this
function instead. This function has a return value. Calling this prevents the need to call
$Aim->getLastReceived()

$Aim->setWarning($wl)
This allows you to update the bots warning level when warned.

$Aim->getBuddies()
Returns the $this->myBuddyList array. Use this instead of modifying the internal variable

$Aim->getPermit()
Returns the $this->myPermitList array. Use this instead of modifying the internal variable

$Aim->getBlocked()
Returns the $this->myBlockedList array. Use this instead of modifying the internal variable

$Aim->warn_user($user,$anon=false)
Warn $user. If anon is set to true, then it warns the user anonomously

$Aim->update_profile($information, $poweredby=false)
Updates Profile to $information.  If $poweredby is true a link to
sourceforge page for this script is appended to profile

$Aim->registerHandler($function_name,$command)
This is by far the best thing about the new release. 
For more information please reas supplement.txt. It is not included here because of the sheer size of the document.
supplement.txt contains full details on using registerHandler and what to expect for each input.


For convenience, I have provided some functions to simplify message processing. 

They can be read about in the file "supplement.txt". I chose not to include the text here because it
is a huge document



There are a few things you should note about AIM
1)An incoming message has HTML tags in it. You are responsible for stripping those tags
2)Outgoing messages can have HTML tags, but will work fine if they don't. To include things
  in the time feild next to the users name, send it as a comment

Conclusion:
The class is released under the LGPL. If you have any bug reports, comments, questions
feature requests, or want to help/show me what you've created with this(I am very interested in this), 
please drop me an email: pickleman78@users.sourceforge.net. This code was written by 
Jeremy(a.k.a pickleman78) and Rajiv M (a.k.a compwiz562).


Special thanks:
I'd like to thank all of the people who have contributed ideas, testing, bug reports, and code additions to
this project. I'd like to especially thank Rajiv, who has done do much for the project, and has kept this documnet
looking nice. He also has done alot of testing of this script too. I'd also like to thank SpazLink for his help in
testing. And finally I'd like to thank Jeffery Grafton, whose script inspired me to start this project.
