# thin

将ThinkPHP的Model拆分，演示代码应该如何降低耦合

主要从通用Model、Validator、DbModel几个角度进行分解，尽可能将职责明确

通用Model具有属性管理、数据校验、场景化等概念；

DbModel强调基于db单表的相关常用操作
