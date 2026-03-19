# 1. 使用官方自带 Apache 的 PHP 8.2 镜像
FROM php:8.2-apache

# 2. 设置工作目录
WORKDIR /var/www/html

# 3. 将当前目录下的所有文件复制到容器中
COPY . /var/www/html/

# 4. 关键步骤：修复权限问题
# chown 将所有权交给 Apache 用户 (www-data)
# chmod 确保 data 文件夹及其内部文件可读写 (775)
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 775 /var/www/html/data

# 5. 开启 Apache 的 Rewrite 模块（如果你有 .htaccess 文件或需要 URL 重写）
RUN a2enmod rewrite

# 6. 告知 Render 监听 80 端口
EXPOSE 80

# 7. 启动 Apache 服务
CMD ["apache2-foreground"]