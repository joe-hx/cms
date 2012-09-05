<?php
namespace Blocks;

/**
 *
 */
class NumberBlock extends BaseBlock
{
	protected $settingsTemplate = '_components/blocks/Number/settings';
	protected $fieldTemplate = '_components/blocks/Number/field';

	/**
	 * @return string|void
	 */
	public function getName()
	{
		return Blocks::t('Number');
	}

	/**
	 * @return array
	 */
	protected function getDefaultSettings()
	{
		return array(
			'min'      => 0,
			'max'      => null,
			'decimals' => 0
		);
	}

	/**
	 * @return null|string
	 */
	public function getColumn()
	{
		return array(PropertyType::Number,
			'min'      => $this->settings['min'],
			'max'      => $this->settings['max'],
			'decimals' => $this->settings['decimals']
		);
	}
}
