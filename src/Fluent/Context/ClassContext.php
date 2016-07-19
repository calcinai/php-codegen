<?php
/**
 * @package    incephption
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\Incephption\Fluent\Context;


use Calcinai\Incephption\Exception\InvalidQualifierException;
use Calcinai\Incephption\Helper\CodeEvaluator;
use Calcinai\Incephption\Node\AbstractNode;
use Calcinai\Incephption\Node\ClassNode;
use Calcinai\Incephption\Node\DocCommentNode;

class ClassContext extends AbstractContext {

    /**
     * @var ClassNode
     */
    private $class;

    public function __construct(AbstractContext $parent_context, ClassNode $class) {
        parent::__construct($parent_context);

        $this->class = $class;
    }

    public function handleExtends($class_name){
        $this->class->setExtends($class_name);
        return $this;
    }

    public function handleUse($class_name){
        $use = ClassNode\UseNode::create($class_name);
        $this->class->addUse($use);

        $this->collectDocs($use);

        return new ClassUseContext($this, $use);
    }

    public function handleConst($name, $value){
        $const = ClassNode\ConstNode::create($name)
            ->setValue($value);

        $this->class->addConst($const);

        $this->collectDocs($const);

        return $this;
    }

    public function handlePrivate($name, $default = null){
        return $this->handleVar($name, $default, AbstractNode::VISIBILITY_PRIVATE);
    }

    public function handleProtected($name, $default = null){
        return $this->handleVar($name, $default, AbstractNode::VISIBILITY_PROTECTED);
    }

    public function handlePublic($name, $default = null){
        return $this->handleVar($name, $default, AbstractNode::VISIBILITY_PUBLIC);
    }

    public function handleVar($name, $default, $visibility = null){
        $property = ClassNode\PropertyNode::create($name)
            ->setVisibility($visibility)
            ->setDefaultValue($default);

        $this->collectDocs($property);

        $this->class->addProperty($property);
        return $this;
    }

    public function handleStatic($name, $default = null){
        $property = ClassNode\PropertyNode::create($name)
            ->setDefaultValue($default);

        $this->collectDocs($property);
        $this->processQueuedQualifiers($property);

        $this->class->addProperty($property);
        return $this;
    }

    /**
     * This one is quite large since it's got to figure out a lot.
     *
     * @param $name
     * @param callable $function
     * @return $this
     * @throws InvalidQualifierException
     */
    public function handleFunction($name, callable $function){

        $method = ClassNode\MethodNode::create($name);
        $this->class->addMethod($method);

        //Need to use reflection for a few things here since the closure behaves a bit differently.
        $reflection = new \ReflectionFunction($function);
        $method->addDoc(DocCommentNode::createFromDocComment($reflection->getDocComment()));
        $variables = ['this' => $reflection->getClosureThis()] + $reflection->getStaticVariables();

        $evaluator = CodeEvaluator::fromReflection($reflection);
        $evaluator->simplify($variables);
        exit;

        $this->processQueuedQualifiers($method);

        //Terminal
        return $this;
    }


}