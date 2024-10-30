create procedure lightningimport_create_db_objects (in _delete_existing_tables bit(1))
begin
	
	if _delete_existing_tables = 1 then		
		drop table if exists lightningimport_product_attributes;      
		drop table if exists lightningimport_product_attribute_mapping; 		
	end if;    
	
    /*Holds unique combinations of product attribute values for searching*/
	create table if not exists `lightningimport_product_attributes` (
		`product_attribute_id` bigint not null auto_increment	
		,`post_id` bigint null
		,`sku` varchar(100) not null
		,`f1` varchar(100) null
        ,`f2` varchar(100) null
        ,`f3` varchar(100) null
		,`f4` varchar(100) null
		,`f5` varchar(100) null
		,`f6` varchar(100) null
		,`f7` varchar(100) null
		,`f8` varchar(100) null
		, primary key(`product_attribute_id`)		
		,index(`sku`)
		,index(`post_id`)
		,UNIQUE KEY (`sku`(15),`f1`(10), `f2`(10), `f3`(10), `f4`(10))
	);
	
	/*Holds names for product attribute columns to allow for dynamic naming of searchable columns*/	
	create table if not exists `lightningimport_product_attribute_mapping` (
		`product_attribute_mapping_id` bigint not null auto_increment		
		,`columnname` varchar(255) not null
		,`columnorder` bigint not null
        ,`attributename` varchar(255) null 
		,`attributedescription` varchar(255) null			
		, primary key(`product_attribute_mapping_id`)
	);		

CREATE TABLE if not exists `lightningimport_temp` (
`import_id` bigint(20) NOT NULL AUTO_INCREMENT,
`PostMeta_Sku` varchar(255) NOT NULL,
`PostTitle` text,
`PostContent` longtext,
`PostExcerpt` text,
`PostContentFiltered` longtext,
`PostMeta_RegularPrice` varchar(255) DEFAULT NULL,
`PostMeta_SalePrice` varchar(255) DEFAULT NULL,
`PostMeta_Weight` varchar(255) DEFAULT NULL,
`PostMeta_Length` varchar(255) DEFAULT NULL,
`PostMeta_Width` varchar(255) DEFAULT NULL,
`PostMeta_Height` varchar(255) DEFAULT NULL,
`PostMeta_Price` varchar(255) DEFAULT NULL,
`PostMeta_Stock` varchar(255) DEFAULT NULL,
`PostMeta_Image` varchar(255) DEFAULT NULL,
`RequestBatchId` varchar(255) DEFAULT NULL,
PRIMARY KEY (`import_id`),
KEY `PostMeta_Sku` (`PostMeta_Sku`)
) ENGINE=InnoDB AUTO_INCREMENT=397 DEFAULT CHARSET=latin1;

CREATE TABLE if not exists `lightningimport_sku` (
`sku` varchar(255) NOT NULL,
`post_id` bigint(20) NOT NULL,
`image` varchar(255) DEFAULT NULL,
PRIMARY KEY (`sku`),
UNIQUE KEY `post_id` (`post_id`),
KEY `image` (`image`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;	
	
	if _delete_existing_tables = 1 then		
		/*Populate the attribute mapping with the column names from the product attributes table*/
		insert lightningimport_product_attribute_mapping(
			columnname
			,columnorder
		)
		select concat('f',cast(option_id as char)),option_id FROM wp_options
		WHERE option_id <= 8;
	end if;

end;