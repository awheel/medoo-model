# Medoo Model

基于 Medooo 封装的简单的数据库访问类库.

## 使用示例

````
<?php

$user = new User(); // User 参见 example/User.php

// 插入
$id = $user->insert(['name' => 'test', 'email' => 'test@hupu.com', 'password' => md5('1234')]);

// 查找
$userInfo = $user->find($id);

// 更新
$count = $user->update(['name' => 'test2'], ['id' => $id]);

// 删除
$status = $user->delete(['id' => $id]);

// 自增
$count = $user->update(['level[+]' => 5], ['id' => $id]);

// 自减
$count = $user->update(['level[-]' => 5], ['id' => $id]);

// 更多使用参考: http://medoo.in/api/
````

