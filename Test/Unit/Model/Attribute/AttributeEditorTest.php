<?php

declare(strict_types=1);

namespace MageOS\Company\Test\Unit\Model\Attribute;

use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Company\Model\Attribute;
use MageOS\Company\Model\Attribute\AttributeEditor;
use MageOS\Company\Model\Attribute\AttributeRepository;
use MageOS\Company\Model\Attribute\FormCodes;
use MageOS\Company\Model\Attribute\InputTypes;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class AttributeEditorTest extends TestCase
{
    /**
     * @var string
     */
    private string $entityType = 'company';

    /**
     * @var AttributeEditor
     */
    private AttributeEditor $editor;

    protected function setUp(): void
    {
        $repository = $this->createMock(AttributeRepository::class);
        $repository->method('getEntityTypeCode')->willReturnCallback(fn (): string => $this->entityType);
        $this->editor = new AttributeEditor(
            $repository,
            new InputTypes(),
            new FormCodes(),
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(ResourceConnection::class),
            new Json()
        );
    }

    /**
     * @param array<string,mixed> $data
     * @return Attribute
     */
    private function attribute(array $data): Attribute
    {
        $attribute = $this->getMockBuilder(Attribute::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $attribute->setData($data);
        // without a resource model the ID field is "id"
        $attribute->setData('id', $data['attribute_id'] ?? null);

        return $attribute;
    }

    public function testCustomAttributeIsFullyEditable(): void
    {
        $permissions = $this->editor->getPermissions($this->attribute([
            'attribute_id' => 5, 'is_user_defined' => 1, 'backend_type' => 'varchar', 'frontend_input' => 'text',
        ]));
        $this->assertSame(
            ['code' => false, 'input' => false, 'required' => true, 'visible' => true, 'validation' => true,
                'default_value' => true, 'forms' => true, 'options' => false, 'grid' => true, 'delete' => true],
            $permissions
        );
    }

    public function testSystemStaticAttributeIsLocked(): void
    {
        $permissions = $this->editor->getPermissions($this->attribute([
            'attribute_id' => 1, 'is_user_defined' => 0, 'backend_type' => 'static', 'frontend_input' => 'text',
            'is_required' => 1,
        ]));
        $this->assertSame([], array_keys(array_filter($permissions)));
    }

    public function testSystemOptionalSelectKeepsOptionsFormsAndGrid(): void
    {
        $permissions = $this->editor->getPermissions($this->attribute([
            'attribute_id' => 2, 'is_user_defined' => 0, 'backend_type' => 'int', 'frontend_input' => 'select',
            'source_model' => Table::class, 'is_required' => 0,
        ]));
        $this->assertSame(['forms', 'options', 'grid'], array_keys(array_filter($permissions)));
    }

    public function testCustomSourceOptionsAreNotEditable(): void
    {
        $permissions = $this->editor->getPermissions($this->attribute([
            'attribute_id' => 3, 'is_user_defined' => 0, 'backend_type' => 'varchar', 'frontend_input' => 'select',
            'source_model' => 'Vendor\\Source\\Locale',
        ]));
        $this->assertFalse($permissions['options']);
    }

    public function testAddressAttributesHaveNoGridFlags(): void
    {
        $this->entityType = 'company_address';
        $permissions = $this->editor->getPermissions($this->attribute([
            'is_user_defined' => 1, 'backend_type' => 'varchar', 'frontend_input' => 'text',
        ]));
        $this->assertFalse($permissions['grid']);
        $this->assertTrue($permissions['code']);
        $this->assertFalse($permissions['delete']);
    }
}
