<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

require_once dirname(__FILE__) . '/PlatformTestBase.php';
require_once dirname(__FILE__) . '/../../../../generator/lib/model/Column.php';
require_once dirname(__FILE__) . '/../../../../generator/lib/model/VendorInfo.php';
require_once dirname(__FILE__) . '/../../../../generator/lib/builder/util/XmlToAppData.php';

/**
 *
 * @package    generator.platform 
 */
class MysqlPlatformTest extends PlatformTestBase
{
	public function testGetSequenceNameDefault()
	{
		$table = new Table('foo');
		$table->setIdMethod(IDMethod::NATIVE);
		$expected = 'foo_SEQ';
		$this->assertEquals($expected, $this->getPlatform()->getSequenceName($table));
	}

	public function testGetSequenceNameCustom()
	{
		$table = new Table('foo');
		$table->setIdMethod(IDMethod::NATIVE);
		$idMethodParameter = new IdMethodParameter();
		$idMethodParameter->setValue('foo_sequence');
		$table->addIdMethodParameter($idMethodParameter);
		$table->setIdMethod(IDMethod::NATIVE);
		$expected = 'foo_sequence';
		$this->assertEquals($expected, $this->getPlatform()->getSequenceName($table));
	}

	/**
	 * @dataProvider providerForTestGetAddTablesDDL
	 */
	public function testGetAddTablesDDL($schema)
	{
		$database = $this->getDatabaseFromSchema($schema);
		$expected = <<<EOF

# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-----------------------------------------------------------------------
-- book
-----------------------------------------------------------------------

DROP TABLE IF EXISTS `book`;

CREATE TABLE `book`
(
	`id` INTEGER NOT NULL AUTO_INCREMENT,
	`title` VARCHAR(255) NOT NULL,
	`author_id` INTEGER,
	PRIMARY KEY (`id`),
	INDEX `book_I_1` (`title`),
	INDEX `book_FI_1` (`author_id`),
	CONSTRAINT `book_FK_1`
		FOREIGN KEY (`author_id`)
		REFERENCES `author` (`id`)
) ENGINE=InnoDB;

-----------------------------------------------------------------------
-- author
-----------------------------------------------------------------------

DROP TABLE IF EXISTS `author`;

CREATE TABLE `author`
(
	`id` INTEGER NOT NULL AUTO_INCREMENT,
	`first_name` VARCHAR(100),
	`last_name` VARCHAR(100),
	PRIMARY KEY (`id`)
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;

EOF;
		$this->assertEquals($expected, $this->getPlatform()->getAddTablesDDL($database));
	}

	/**
	 * @dataProvider providerForTestGetAddTablesSkipSQLDDL
	 */
	public function testGetAddTablesSkipSQLDDL($schema)
	{
		$database = $this->getDatabaseFromSchema($schema);
		$expected = "
# This is a fix for InnoDB in MySQL >= 4.1.x
# It \"suspends judgement\" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
";
		$this->assertEquals($expected, $this->getPlatform()->getAddTablesDDL($database));
	}
	
	/**
	 * @dataProvider providerForTestGetAddTableDDLSimplePK
	 */
	public function testGetAddTableDDLSimplePK($schema)
	{
		$table = $this->getTableFromSchema($schema);
		$expected = "
CREATE TABLE `foo`
(
	`id` INTEGER NOT NULL AUTO_INCREMENT,
	`bar` VARCHAR(255) NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB COMMENT='This is foo table';
";
		$this->assertEquals($expected, $this->getPlatform()->getAddTableDDL($table));
	}

	/**
	 * @dataProvider providerForTestGetAddTableDDLCompositePK
	 */
	public function testGetAddTableDDLCompositePK($schema)
	{
		$table = $this->getTableFromSchema($schema);
		$expected = "
CREATE TABLE `foo`
(
	`foo` INTEGER NOT NULL,
	`bar` INTEGER NOT NULL,
	`baz` VARCHAR(255) NOT NULL,
	PRIMARY KEY (`foo`,`bar`)
) ENGINE=InnoDB;
";
		$this->assertEquals($expected, $this->getPlatform()->getAddTableDDL($table));
	}

	/**
	 * @dataProvider providerForTestGetAddTableDDLUniqueIndex
	 */
	public function testGetAddTableDDLUniqueIndex($schema)
	{
		$table = $this->getTableFromSchema($schema);
		$expected = "
CREATE TABLE `foo`
(
	`id` INTEGER NOT NULL AUTO_INCREMENT,
	`bar` INTEGER,
	PRIMARY KEY (`id`),
	UNIQUE INDEX `foo_U_1` (`bar`)
) ENGINE=InnoDB;
";
		$this->assertEquals($expected, $this->getPlatform()->getAddTableDDL($table));
	}

	public function testGetAddTableDDLIndex()
	{
		$schema = <<<EOF
<database name="test">
	<table name="foo">
		<column name="id" primaryKey="true" type="INTEGER" autoIncrement="true" />
		<column name="bar" type="INTEGER" />
		<index>
			<index-column name="bar" />
		</index>
	</table>
</database>
EOF;
		$table = $this->getTableFromSchema($schema);
		$expected = "
CREATE TABLE `foo`
(
	`id` INTEGER NOT NULL AUTO_INCREMENT,
	`bar` INTEGER,
	PRIMARY KEY (`id`),
	INDEX `foo_I_1` (`bar`)
) ENGINE=InnoDB;
";
		$this->assertEquals($expected, $this->getPlatform()->getAddTableDDL($table));
	}

	public function testGetAddTableDDLForeignKey()
	{
		$schema = <<<EOF
<database name="test">
	<table name="foo">
		<column name="id" primaryKey="true" type="INTEGER" autoIncrement="true" />
		<column name="bar_id" type="INTEGER" />
		<foreign-key foreignTable="bar">
			<reference local="bar_id" foreign="id" />
		</foreign-key>
	</table>
	<table name="bar">
		<column name="id" primaryKey="true" type="INTEGER" autoIncrement="true" />
	</table>
</database>
EOF;
		$table = $this->getTableFromSchema($schema);
		$expected = "
CREATE TABLE `foo`
(
	`id` INTEGER NOT NULL AUTO_INCREMENT,
	`bar_id` INTEGER,
	PRIMARY KEY (`id`),
	INDEX `foo_FI_1` (`bar_id`),
	CONSTRAINT `foo_FK_1`
		FOREIGN KEY (`bar_id`)
		REFERENCES `bar` (`id`)
) ENGINE=InnoDB;
";
		$this->assertEquals($expected, $this->getPlatform()->getAddTableDDL($table));
	}

	public function testGetAddTableDDLEngine()
	{
		$schema = <<<EOF
<database name="test">
	<table name="foo">
		<column name="id" primaryKey="true" type="INTEGER" autoIncrement="true" />
	</table>
</database>
EOF;
		$platform = new MysqlPlatform();
		$platform->setTableEngineKeyword('TYPE');
		$platform->setDefaultTableEngine('MEMORY');
		$xtad = new XmlToAppData($platform);
		$appData = $xtad->parseString($schema);
		$table = $appData->getDatabase()->getTable('foo');
		$expected = "
CREATE TABLE `foo`
(
	`id` INTEGER NOT NULL AUTO_INCREMENT,
	PRIMARY KEY (`id`)
) TYPE=MEMORY;
";
		$this->assertEquals($expected, $platform->getAddTableDDL($table));
	}

	public function testGetAddTableDDLVendor()
	{
		$schema = <<<EOF
<database name="test">
	<table name="foo">
		<column name="id" primaryKey="true" type="INTEGER" autoIncrement="true" />
		<vendor type="mysql">
			<parameter name="Engine" value="MyISAM"/>
			<parameter name="Charset" value="utf8"/>
		</vendor>
	</table>
</database>
EOF;
		$table = $this->getTableFromSchema($schema);
		$expected = "
CREATE TABLE `foo`
(
	`id` INTEGER NOT NULL AUTO_INCREMENT,
	PRIMARY KEY (`id`)
) ENGINE=MyISAM CHARACTER SET='utf8';
";
		$this->assertEquals($expected, $this->getPlatform()->getAddTableDDL($table));
	}
	
	public function testGetDropTableDDL()
	{
		$table = new Table('foo');
		$expected = "
DROP TABLE IF EXISTS `foo`;
";
		$this->assertEquals($expected, $this->getPlatform()->getDropTableDDL($table));
	}
	
	public function testGetColumnDDL()
	{
		$column = new Column('foo');
		$column->getDomain()->copy($this->getPlatform()->getDomainForType('DOUBLE'));
		$column->getDomain()->replaceScale(2);
		$column->getDomain()->replaceSize(3);
		$column->setNotNull(true);
		$column->getDomain()->setDefaultValue(new ColumnDefaultValue(123, ColumnDefaultValue::TYPE_VALUE));
		$expected = '`foo` DOUBLE(3,2) DEFAULT 123 NOT NULL';
		$this->assertEquals($expected, $this->getPlatform()->getColumnDDL($column));
	}
	
	public function testGetColumnDDLCharsetVendor()
	{
		$column = new Column('foo');
		$column->getDomain()->copy($this->getPlatform()->getDomainForType('LONGVARCHAR'));
		$vendor = new VendorInfo('mysql');
		$vendor->setParameter('Charset', 'greek');
		$column->addVendorInfo($vendor);
		$expected = '`foo` TEXT CHARACTER SET \'greek\'';
		$this->assertEquals($expected, $this->getPlatform()->getColumnDDL($column));
	}

	public function testGetColumnDDLCharsetCollation()
	{
		$column = new Column('foo');
		$column->getDomain()->copy($this->getPlatform()->getDomainForType('LONGVARCHAR'));
		$vendor = new VendorInfo('mysql');
		$vendor->setParameter('Collate', 'latin1_german2_ci');
		$column->addVendorInfo($vendor);
		$expected = '`foo` TEXT COLLATE \'latin1_german2_ci\'';
		$this->assertEquals($expected, $this->getPlatform()->getColumnDDL($column));

		$column = new Column('foo');
		$column->getDomain()->copy($this->getPlatform()->getDomainForType('LONGVARCHAR'));
		$vendor = new VendorInfo('mysql');
		$vendor->setParameter('Collation', 'latin1_german2_ci');
		$column->addVendorInfo($vendor);
		$expected = '`foo` TEXT COLLATE \'latin1_german2_ci\'';
		$this->assertEquals($expected, $this->getPlatform()->getColumnDDL($column));
	}

	public function testGetColumnDDLComment()
	{
		$column = new Column('foo');
		$column->getDomain()->copy($this->getPlatform()->getDomainForType('INTEGER'));
		$column->setDescription('This is column Foo');
		$expected = '`foo` INTEGER COMMENT \'This is column Foo\'';
		$this->assertEquals($expected, $this->getPlatform()->getColumnDDL($column));
	}
	
	public function testGetPrimaryKeyDDLSimpleKey()
	{
		$table = new Table('foo');
		$column = new Column('bar');
		$column->setPrimaryKey(true);
		$table->addColumn($column);
		$expected = 'PRIMARY KEY (`bar`)';
		$this->assertEquals($expected, $this->getPlatform()->getPrimaryKeyDDL($table));
	}

	public function testGetPrimaryKeyDDLCompositeKey()
	{
		$table = new Table('foo');
		$column1 = new Column('bar1');
		$column1->setPrimaryKey(true);
		$table->addColumn($column1);
		$column2 = new Column('bar2');
		$column2->setPrimaryKey(true);
		$table->addColumn($column2);
		$expected = 'PRIMARY KEY (`bar1`,`bar2`)';
		$this->assertEquals($expected, $this->getPlatform()->getPrimaryKeyDDL($table));
	}

	/**
	 * @dataProvider providerForTestGetIndicesDDL
	 */
	public function testAddIndicesDDL($table)
	{
		$expected = "
CREATE INDEX `babar` ON `foo` (`bar1`,`bar2`);

CREATE INDEX `foo_index` ON `foo` (`bar1`);
";
		$this->assertEquals($expected, $this->getPLatform()->getAddIndicesDDL($table));
	}
	
	/**
	 * @dataProvider providerForTestGetIndexDDL
	 */
	public function testAddIndexDDL($index)
	{
		$expected = "
CREATE INDEX `babar` ON `foo` (`bar1`,`bar2`);
";
		$this->assertEquals($expected, $this->getPLatform()->getAddIndexDDL($index));
	}

	/**
	 * @dataProvider providerForTestGetIndexDDL
	 */
	public function testDropIndexDDL($index)
	{
		$expected = "
ALTER TABLE `foo` DROP INDEX `babar`;
";
		$this->assertEquals($expected, $this->getPLatform()->getDropIndexDDL($index));
	}
	
	/**
	 * @dataProvider providerForTestGetIndexDDL
	 */
	public function testGetIndexDDL($index)
	{
		$expected = 'INDEX `babar` (`bar1`, `bar2`)';
		$this->assertEquals($expected, $this->getPLatform()->getIndexDDL($index));
	}

	public function testGetIndexDDLKeySize()
	{
		$table = new Table('foo');
		$column1 = new Column('bar1');
		$column1->getDomain()->copy($this->getPlatform()->getDomainForType('VARCHAR'));
		$column1->setSize(5);
		$table->addColumn($column1);
		$index = new Index('bar_index');
		$index->addColumn($column1);
		$table->addIndex($index);
		$expected = 'INDEX `bar_index` (`bar1`(5))';
		$this->assertEquals($expected, $this->getPLatform()->getIndexDDL($index));
	}

	public function testGetIndexDDLFulltext()
	{
		$table = new Table('foo');
		$column1 = new Column('bar1');
		$column1->getDomain()->copy($this->getPlatform()->getDomainForType('LONGVARCHAR'));
		$table->addColumn($column1);
		$index = new Index('bar_index');
		$index->addColumn($column1);
		$vendor = new VendorInfo('mysql');
		$vendor->setParameter('Index_type', 'FULLTEXT');
		$index->addVendorInfo($vendor);
		$table->addIndex($index);
		$expected = 'FULLTEXT INDEX `bar_index` (`bar1`)';
		$this->assertEquals($expected, $this->getPLatform()->getIndexDDL($index));
	}

	/**
	 * @dataProvider providerForTestGetUniqueDDL
	 */
	public function testGetUniqueDDL($index)
	{
		$expected = 'UNIQUE INDEX `babar` (`bar1`, `bar2`)';
		$this->assertEquals($expected, $this->getPLatform()->getUniqueDDL($index));
	}

	/**
	 * @dataProvider providerForTestGetForeignKeysDDL
	 */
	public function testGetAddForeignKeysDDL($table)
	{
		$expected = "
ALTER TABLE `foo` ADD CONSTRAINT `foo_bar_FK`
	FOREIGN KEY (`bar_id`)
	REFERENCES `bar` (`id`)
	ON DELETE CASCADE;

ALTER TABLE `foo` ADD CONSTRAINT `foo_baz_FK`
	FOREIGN KEY (`baz_id`)
	REFERENCES `baz` (`id`)
	ON DELETE SET NULL;
";
		$this->assertEquals($expected, $this->getPLatform()->getAddForeignKeysDDL($table));
	}
	
	/**
	 * @dataProvider providerForTestGetForeignKeyDDL
	 */
	public function testGetAddForeignKeyDDL($fk)
	{
		$expected = "
ALTER TABLE `foo` ADD CONSTRAINT `foo_bar_FK`
	FOREIGN KEY (`bar_id`)
	REFERENCES `bar` (`id`)
	ON DELETE CASCADE;
";
		$this->assertEquals($expected, $this->getPLatform()->getAddForeignKeyDDL($fk));
	}

	/**
	 * @dataProvider providerForTestGetForeignKeyDDL
	 */
	public function testGetDropForeignKeyDDL($fk)
	{
		$expected = "
ALTER TABLE `foo` DROP FOREIGN KEY `foo_bar_FK`;
";
		$this->assertEquals($expected, $this->getPLatform()->getDropForeignKeyDDL($fk));
	}
	
	/**
	 * @dataProvider providerForTestGetForeignKeyDDL
	 */
	public function testGetForeignKeyDDL($fk)
	{
		$expected = "CONSTRAINT `foo_bar_FK`
	FOREIGN KEY (`bar_id`)
	REFERENCES `bar` (`id`)
	ON DELETE CASCADE";
		$this->assertEquals($expected, $this->getPLatform()->getForeignKeyDDL($fk));
	}


}
