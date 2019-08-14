<?php
/**
 * @category   XpressBuy
 * @package    XpressBuy_ProductVariants
 */


class XpressBuy_ProductVariants_Model_Api extends Mage_Catalog_Model_Api_Resource
{
    /** Get xpressbuy product information */
    public function variants($productId, $store = null)
    {
        $product = Mage::helper('catalog/product')->getProduct($productId, $this->_getStoreId($store));
        if (is_null($product->getId())) {
            $this->_fault('product_not_exists');
        }

        if (!$product->getId()) {
            $this->_fault('not_exists');
        }

        try {
            if ($product->getTypeId() != "configurable") {
                return null;
            }
            $res["variants"] = array();
            // Collect options applicable to the configurable product
            $productAttributeOptions = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product);
            $attributeOptions = array();
            $otherOptions = array();
            foreach ($productAttributeOptions as $productAttribute) {
                foreach ($productAttribute['values'] as $attribute) {
                    $attributeOptions[$productAttribute['label']] = $productAttribute['label'];
                    $attr = new stdClass();
                    $attr->code = $attribute['value_index'];
                    $attr->value = $attribute['store_label'];
                    $attr->type = $productAttribute['label'];
                    $otherOptions[$attr->code] = $attr;
                }
            }

            $attr_types = array_keys($attributeOptions);
            if ($product->getTypeId() == "configurable") {
                $conf = Mage::getModel('catalog/product_type_configurable')->setProduct($product);
                $simple_collection = $conf->getUsedProductCollection()->addAttributeToSelect('*')->addFilterByRequiredOptions();
                foreach ($simple_collection as $simple_product) {
                    $prod_options = [];
                    $variant_prod = $this->getVariantProduct($simple_product, $attr_types, $otherOptions, $prod_options);
                    array_push($res['variants'], $variant_prod);
                }
            }
            return $res;
        }
        catch (Mage_Core_Exception $e)
        {
            $this->_fault('Error in finding the variants');
        }
        return null;
    }

    /**
     * @param $simple_product
     * @param $attr_types
     * @param $extraOptions
     * @param $prod_options
     * @return array
     */
    public function getVariantProduct($simple_product, $attr_types, $extraOptions, $prod_options)
    {
        $product_id = $simple_product->getId();
        foreach ($attr_types as $attr_type) {
            $attr_code = Mage::getResourceModel('catalog/product')->getAttributeRawValue($product_id, $attr_type);
            $prod_options[] = $extraOptions[$attr_code];
        }
        $final_price = $simple_product->getFinalPrice();
        $regular_price = $simple_product->getPrice();
        $special_price = null;
        if ($final_price != $regular_price) {
            $special_price = $final_price;
        }

        $image_class = Mage::getModel('catalog/product_attribute_media_api');
        $images_with_base_url = array();
        foreach ($image_class->items($simple_product->getId()) as $base_image){
            // Set base image
            $base_image['url'] = (string)Mage::Helper('catalog/image')->init($simple_product, 'image', $base_image['file']);
            $images_with_base_url[] = $base_image;
        }
        $variant_prod = array(
            'product_id' => $product_id,
            'sku' => $simple_product->getSku(),
            'name' => $simple_product->getName(),
            'price' => $regular_price,
            'special_price' => ($special_price),
            'images' => $images_with_base_url,
            'attributes' => $prod_options
        );
        return $variant_prod;
    }

}
