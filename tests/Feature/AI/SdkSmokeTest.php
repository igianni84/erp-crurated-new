<?php

namespace Tests\Feature\AI;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\BooleanType;
use Illuminate\JsonSchema\Types\IntegerType;
use Illuminate\JsonSchema\Types\NumberType;
use Illuminate\JsonSchema\Types\StringType;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Smoke test verifying all Laravel AI SDK contracts, attributes, and types exist.
 *
 * SDK migration tables:
 *  - agent_conversations (id, user_id, title, created_at, updated_at)
 *  - agent_conversation_messages (id, conversation_id, user_id, agent, role, content,
 *    attachments, tool_calls, tool_results, usage, meta, created_at, updated_at)
 */
class SdkSmokeTest extends TestCase
{
    #[Test]
    public function sdk_contracts_exist(): void
    {
        $this->assertTrue(interface_exists(Agent::class), 'Agent contract missing');
        $this->assertTrue(interface_exists(Conversational::class), 'Conversational contract missing');
        $this->assertTrue(interface_exists(HasTools::class), 'HasTools contract missing');
        $this->assertTrue(interface_exists(Tool::class), 'Tool contract missing');
    }

    #[Test]
    public function sdk_attributes_exist(): void
    {
        $this->assertTrue(class_exists(MaxSteps::class), 'MaxSteps attribute missing');
        $this->assertTrue(class_exists(Model::class), 'Model attribute missing');
        $this->assertTrue(class_exists(Provider::class), 'Provider attribute missing');
        $this->assertTrue(class_exists(Temperature::class), 'Temperature attribute missing');
    }

    #[Test]
    public function lab_enum_has_anthropic_case(): void
    {
        $this->assertInstanceOf(\BackedEnum::class, Lab::Anthropic);
        $this->assertSame('anthropic', Lab::Anthropic->value);
    }

    #[Test]
    public function remembers_conversations_trait_exists(): void
    {
        $this->assertTrue(trait_exists(RemembersConversations::class), 'RemembersConversations trait missing');
    }

    #[Test]
    public function promptable_trait_exists(): void
    {
        $this->assertTrue(trait_exists(Promptable::class), 'Promptable trait missing');
    }

    #[Test]
    public function tool_request_class_exists(): void
    {
        $this->assertTrue(class_exists(Request::class), 'Tools\\Request class missing');

        $request = new Request(['foo' => 'bar']);
        $this->assertSame('bar', $request['foo']);
    }

    #[Test]
    public function json_schema_interface_exists(): void
    {
        $this->assertTrue(interface_exists(JsonSchema::class), 'JsonSchema interface missing');
    }

    #[Test]
    public function json_schema_types_support_enum_and_default(): void
    {
        $schema = new JsonSchemaTypeFactory;

        // String type supports enum() and default()
        $stringType = $schema->string();
        $this->assertInstanceOf(StringType::class, $stringType);
        $this->assertTrue(method_exists($stringType, 'enum'), 'StringType missing enum()');
        $this->assertTrue(method_exists($stringType, 'default'), 'StringType missing default()');

        // Integer type supports enum() and default()
        $intType = $schema->integer();
        $this->assertInstanceOf(IntegerType::class, $intType);
        $this->assertTrue(method_exists($intType, 'enum'), 'IntegerType missing enum()');
        $this->assertTrue(method_exists($intType, 'default'), 'IntegerType missing default()');

        // Number type supports default()
        $numType = $schema->number();
        $this->assertInstanceOf(NumberType::class, $numType);
        $this->assertTrue(method_exists($numType, 'default'), 'NumberType missing default()');

        // Boolean type supports default()
        $boolType = $schema->boolean();
        $this->assertInstanceOf(BooleanType::class, $boolType);
        $this->assertTrue(method_exists($boolType, 'default'), 'BooleanType missing default()');

        // Verify they return fluent (static) without errors
        $result = $schema->string()->enum(['a', 'b'])->default('a');
        $this->assertInstanceOf(StringType::class, $result);
    }

    #[Test]
    public function tool_description_and_handle_return_stringable_or_string(): void
    {
        $reflection = new \ReflectionMethod(Tool::class, 'description');
        $returnType = $reflection->getReturnType();
        $this->assertInstanceOf(\ReflectionUnionType::class, $returnType);

        $typeNames = array_map(fn (\ReflectionNamedType $t) => $t->getName(), $returnType->getTypes());
        $this->assertContains('Stringable', $typeNames);
        $this->assertContains('string', $typeNames);

        $handleReflection = new \ReflectionMethod(Tool::class, 'handle');
        $handleReturnType = $handleReflection->getReturnType();
        $this->assertInstanceOf(\ReflectionUnionType::class, $handleReturnType);

        $handleTypeNames = array_map(fn (\ReflectionNamedType $t) => $t->getName(), $handleReturnType->getTypes());
        $this->assertContains('Stringable', $handleTypeNames);
        $this->assertContains('string', $handleTypeNames);
    }

    #[Test]
    public function agent_response_usage_has_token_fields(): void
    {
        // Usage uses camelCase properties: promptTokens, completionTokens
        // toArray() outputs snake_case: prompt_tokens, completion_tokens
        $usage = new Usage(
            promptTokens: 100,
            completionTokens: 50,
        );

        $this->assertSame(100, $usage->promptTokens);
        $this->assertSame(50, $usage->completionTokens);

        $array = $usage->toArray();
        $this->assertArrayHasKey('prompt_tokens', $array);
        $this->assertArrayHasKey('completion_tokens', $array);
        $this->assertSame(100, $array['prompt_tokens']);
        $this->assertSame(50, $array['completion_tokens']);
    }
}
