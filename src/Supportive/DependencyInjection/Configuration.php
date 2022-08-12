<?php

declare(strict_types=1);

namespace Qossmic\Deptrac\Supportive\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use function array_key_exists;
use function is_array;

/**
 * @psalm-suppress all
 */
class Configuration implements ConfigurationInterface
{
    public const ALLOWED_EMITTER_TYPES = [
        EmitterTypes::CLASS_TOKEN,
        EmitterTypes::CLASS_SUPERGLOBAL_TOKEN,
        EmitterTypes::FILE_TOKEN,
        EmitterTypes::FUNCTION_TOKEN,
        EmitterTypes::FUNCTION_CALL,
        EmitterTypes::FUNCTION_SUPERGLOBAL_TOKEN,
        EmitterTypes::USE_TOKEN,
    ];

    public const DEFAULT_EMITTER_TYPES = [
        EmitterTypes::CLASS_TOKEN,
        EmitterTypes::USE_TOKEN,
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder('deptrac');
        $rootNode = $builder->getRootNode();

        $this->appendAnalysedPaths($rootNode);
        $this->appendExcludePatterns($rootNode);
        $this->appendLayers($rootNode);
        $this->appendRuleset($rootNode);
        $this->appendSkippedViolations($rootNode);
        $this->appendFormatters($rootNode);
        $this->appendEmitterTypes($rootNode);
        $this->appendIgnoreUncoveredInternalClasses($rootNode);
        $this->appendUseRelativePathFromDepfile($rootNode);

        return $builder;
    }

    private function appendAnalysedPaths(ArrayNodeDefinition $node): void
    {
        $node
            ->fixXmlConfig('path')
            ->children()
                ->arrayNode('paths')
                    ->info('List of paths to search for PHP files to be analysed.')
                    ->addDefaultChildrenIfNoneSet()
                    ->requiresAtLeastOneElement()
                    ->scalarPrototype()
                        ->cannotBeEmpty()
                        ->defaultValue('src/')
                    ->end()
                ->end()
            ->end();
    }

    private function appendExcludePatterns(ArrayNodeDefinition $node): void
    {
        $node
            ->fixXmlConfig('exclude_file')
            ->children()
                ->arrayNode('exclude_files')
                    ->info('List of regular expressions for excluding files or directories from being analysed')
                    ->example('#.*Test\.php$#')
                    ->scalarPrototype()
                        ->cannotBeEmpty()
                    ->end()
                ->end()
            ->end();
    }

    private function appendLayers(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('layers')
                    ->useAttributeAsKey('name', false)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('name')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->arrayNode('collectors')
                                ->isRequired()
                                ->requiresAtLeastOneElement()
                                ->arrayPrototype()
                                    ->ignoreExtraKeys(false)
                                    ->children()
                                        ->scalarNode('type')->isRequired()->end()
                                        ->arrayNode('attributes')
                                            ->variablePrototype()->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('attributes')
                                ->variablePrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function appendRuleset(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('ruleset')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->scalarPrototype()->end()
                    ->end()
                ->end()
            ->end();
    }

    private function appendSkippedViolations(ArrayNodeDefinition $node): void
    {
        $node
            ->fixXmlConfig('skip_violation')
            ->children()
                ->arrayNode('skip_violations')
                    ->info('Skip violations matching a regular expressions')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->scalarPrototype()->end()
                    ->end()
                ->end()
            ->end();
    }

    private function appendFormatters(ArrayNodeDefinition $node): void
    {
        $node
            ->fixXmlConfig('formatter')
            ->children()
                ->arrayNode('formatters')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('graphviz')
                            ->info('Configure Graphviz output formatters')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('hidden_layers')
                                    ->info('Specify any layer name, that you wish to exclude from the output')
                                    ->scalarPrototype()->end()
                                ->end()
                                ->arrayNode('groups')
                                    ->info('Combine multiple layers to a group')
                                    ->useAttributeAsKey('name')
                                    ->arrayPrototype()
                                        ->scalarPrototype()->end()
                                    ->end()
                                ->end()
                                ->booleanNode('point_to_groups')
                                    ->info('When a layer is part of a group, should edges point towards the group or the layer?')
                                    ->defaultFalse()
                                ->end()
                            ->end()
                            ->beforeNormalization()
                                ->ifTrue(static function ($v) {
                                    return is_array($v) && array_key_exists('pointToGroups', $v);
                                })
                                ->then(static function ($v) {
                                    $v['point_to_groups'] = $v['pointToGroups'];
                                    unset($v['pointToGroups']);

                                    return $v;
                                })
                            ->end()
                        ->end()
                        ->arrayNode('codeclimate')
                            ->addDefaultsIfNotSet()
                            ->info('Configure Codeclimate output formatters')
                            ->children()
                                ->arrayNode('severity')
                                    ->info('Map how failures, skipped and uncovered dependencies map to severity in CodeClimate')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->enumNode('failure')->values(['info', 'minor', 'major', 'critical', 'blocker'])->defaultValue('major')->end()
                                        ->enumNode('skipped')->values(['info', 'minor', 'major', 'critical', 'blocker'])->defaultValue('minor')->end()
                                        ->enumNode('uncovered')->values(['info', 'minor', 'major', 'critical', 'blocker'])->defaultValue('info')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function appendEmitterTypes(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('analyser')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('types')
                            ->isRequired()
                            ->defaultValue(self::DEFAULT_EMITTER_TYPES)
                            ->scalarPrototype()
                                ->beforeNormalization()
                                    ->ifNotInArray(self::ALLOWED_EMITTER_TYPES)
                                    ->thenInvalid('Invalid type %s')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function appendIgnoreUncoveredInternalClasses(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->booleanNode('ignore_uncovered_internal_classes')
                    ->defaultTrue()
                ->end()
            ->end();
    }

    private function appendUseRelativePathFromDepfile(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->booleanNode('use_relative_path_from_depfile')
                    ->defaultTrue()
                ->end()
            ->end();
    }
}