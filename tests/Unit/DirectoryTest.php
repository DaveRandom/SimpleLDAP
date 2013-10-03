<?php

namespace SimpleLDAPTest\Unit;

use SimpleLDAP\Directory;

class SignatureTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers SimpleLDAP\Directory::__construct
     * @covers SimpleLDAP\Directory::getHost
     * @covers SimpleLDAP\Directory::getPort
     * @covers SimpleLDAP\Directory::getUser
     */
    public function testConstructURI()
    {
        $ldapi = $this->getMock('\\LDAPi\\Directory');

        $directory = new Directory($ldapi, 'ldap://user@test.com:389');
        $this->assertEquals('test.com', $directory->getHost());
        $this->assertEquals(389, $directory->getPort());
        $this->assertEquals('user', $directory->getUser());
    }

    /**
     * @covers SimpleLDAP\Directory::__construct
     * @covers SimpleLDAP\Directory::getSecurityType
     */
    public function testConstructSecurityType()
    {
        $ldapi = $this->getMock('\\LDAPi\\Directory');

        $directory = new Directory($ldapi, 'ldap://test.com');
        $this->assertEquals(Directory::SECURITY_NONE, $directory->getSecurityType());

        $directory = new Directory($ldapi, 'ldaps://test.com');
        $this->assertEquals(Directory::SECURITY_SSL, $directory->getSecurityType());

        $directory = new Directory($ldapi, 'tls://test.com');
        $this->assertEquals(Directory::SECURITY_TLS, $directory->getSecurityType());

        $directory = new Directory($ldapi, 'test.com');
        $this->assertEquals(Directory::SECURITY_NONE, $directory->getSecurityType());
    }

    /**
     * @covers SimpleLDAP\Directory::__construct
     */
    public function testConstructUserOverride()
    {
        $ldapi = $this->getMock('\\LDAPi\\Directory');

        $directory = new Directory($ldapi, 'ldap://user@test.com', 'otheruser');
        $this->assertEquals('otheruser', $directory->getUser());
    }

    /**
     * @covers SimpleLDAP\Directory::__construct
     */
    public function testConstructConnect()
    {
        $ldapi = $this->getMock('\\LDAPi\\Directory');

        $ldapi->expects($this->once())
              ->method('connect')
              ->with($this->equalTo('ldap://test.com:389'));

        $directory = new Directory($ldapi, 'ldap://test.com:389');
    }

    /**
     * @covers SimpleLDAP\Directory::__construct
     */
    public function testConstructBind()
    {
        $ldapi = $this->getMock('\\LDAPi\\Directory');

        $ldapi->expects($this->once())
              ->method('bind')
              ->with($this->equalTo('user'), $this->equalTo('pass'));

        $directory = new Directory($ldapi, 'ldap://user:pass@test.com:389');
    }

    /**
     * @covers SimpleLDAP\Directory::__construct
     */
    public function testConstructPasswordOverride()
    {
        $ldapi = $this->getMock('\\LDAPi\\Directory');

        $ldapi->expects($this->once())
              ->method('bind')
              ->with($this->equalTo('user'), $this->equalTo('password'));

        $directory = new Directory($ldapi, 'ldap://user:pass@test.com:389', null, 'password');
    }

    /**
     * @covers SimpleLDAP\Directory::__construct
     */
    public function testConstructSetOpt()
    {
        $ldapi = $this->getMock('\\LDAPi\\Directory');

        $ldapi->expects($this->once())
              ->method('setOption')
              ->with($this->equalTo(1), $this->equalTo(2));

        $directory = new Directory($ldapi, 'ldap://test.com:389', null, null, [1 => 2]);
    }

    /**
     * @covers SimpleLDAP\Directory::add
     */
    public function testAdd()
    {
        $dn = 'cn=Dave,cn=Users';
        $attrs = ['attr' => 'val'];

        $ldapi = $this->getMock('\\LDAPi\\Directory');

        $ldapi->expects($this->once())
              ->method('add')
              ->with($this->equalTo($dn), $this->equalTo($attrs));

        $directory = new Directory($ldapi, 'test.com');
        $directory->add($dn, $attrs);
    }

    /**
     * @covers SimpleLDAP\Directory::delete
     */
    public function testDelete()
    {
        $dn = 'cn=Dave,cn=Users';

        $ldapi = $this->getMock('\\LDAPi\\Directory');

        $ldapi->expects($this->once())
              ->method('delete')
              ->with($this->equalTo($dn));

        $directory = new Directory($ldapi, 'test.com');
        $directory->delete($dn);
    }

    /**
     * @covers SimpleLDAP\Directory::getOption
     */
    public function testGetOption()
    {
        $option = 1;
        $value = 'test';

        $ldapi = $this->getMock('\\LDAPi\\Directory');

        $ldapi->expects($this->once())
              ->method('getOption')
              ->with($this->equalTo($option))
              ->will($this->returnValue($value));

        $directory = new Directory($ldapi, 'test.com');
        $ret = $directory->getOption($option);

        $this->assertEquals($value, $ret);
    }

    /**
     * @covers SimpleLDAP\Directory::listChildren
     */
    public function testListChildren()
    {
        $dn = 'cn=Dave,cn=Users';
        $filter = 'objectClass=Message';
        $attrs = ['foo'];

        $ldapi = $this->getMock('\\LDAPi\\Directory');
        $ldapiresult = $this->getMockBuilder('\\LDAPi\\ResultSet')
                            ->disableOriginalConstructor()
                            ->getMock();

        $ldapi->expects($this->once())
              ->method('listChildren')
              ->with($this->equalTo($dn), $this->equalTo($filter), $this->equalTo($attrs))
              ->will($this->returnValue($ldapiresult));

        $directory = new Directory($ldapi, 'test.com');
        $ret = $directory->listChildren($dn, $filter, $attrs);

        $this->assertInstanceOf('\\SimpleLDAP\\ResultSet', $ret);
    }

    /**
     * @covers SimpleLDAP\Directory::read
     */
    public function testRead()
    {
        $dn = 'cn=Dave,cn=Users';
        $attrs = ['foo'];

        $ldapi = $this->getMock('\\LDAPi\\Directory');
        $ldapiresult = $this->getMockBuilder('\\LDAPi\\ResultSet')
                            ->disableOriginalConstructor()
                            ->getMock();

        $ldapi->expects($this->once())
              ->method('read')
              ->with($this->equalTo($dn), $this->any(), $this->equalTo($attrs))
              ->will($this->returnValue($ldapiresult));

        $directory = new Directory($ldapi, 'test.com');
        $ret = $directory->read($dn, $attrs);

        $this->assertInstanceOf('\\SimpleLDAP\\ResultSet', $ret);
    }

    /**
     * @covers SimpleLDAP\Directory::search
     */
    public function testSearch()
    {
        $dn = 'cn=Dave,cn=Users';
        $filter = 'objectClass=Message';
        $attrs = ['foo'];

        $ldapi = $this->getMock('\\LDAPi\\Directory');
        $ldapiresult = $this->getMockBuilder('\\LDAPi\\ResultSet')
                            ->disableOriginalConstructor()
                            ->getMock();

        $ldapi->expects($this->once())
              ->method('search')
              ->with($this->equalTo($dn), $this->equalTo($filter), $this->equalTo($attrs))
              ->will($this->returnValue($ldapiresult));

        $directory = new Directory($ldapi, 'test.com');
        $ret = $directory->search($dn, $filter, $attrs);

        $this->assertInstanceOf('\\SimpleLDAP\\ResultSet', $ret);
    }
}
